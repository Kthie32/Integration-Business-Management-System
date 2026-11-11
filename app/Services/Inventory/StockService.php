<?php

namespace App\Services\Inventory;

use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\InsufficientStock;
use App\Models\Item;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only way stock changes. Every change writes an immutable movement and updates the item's balance in
 * that warehouse in the same transaction, with the balance row locked (SELECT ... FOR UPDATE).
 *
 * Valuation is weighted average cost per item per warehouse: receipts blend their cost into the average,
 * issues leave at the current average, and reversals undo a movement at that movement's own cost.
 *
 * Other modules call receive(), issue() and reverseFor(); for example invoices issue stock with
 * StockMovementType::SaleIssue and reverse it when voided, and requisitions issue stock when fulfilled.
 */
class StockService
{
    public const QUANTITY_SCALE = 3;

    public const COST_SCALE = 4;

    private const WORKING_SCALE = 10;

    public function __construct(private LowStockNotifier $lowStockNotifier) {}

    /**
     * Add stock (goods received, adjustment increase, return, opening balance, transfer in).
     */
    public function receive(
        Item $item,
        Warehouse $warehouse,
        string $quantity,
        string $unitCost,
        StockMovementType $type = StockMovementType::GoodsReceived,
        ?Model $reference = null,
        ?string $notes = null,
        ?CarbonInterface $movedAt = null,
    ): StockMovement {
        if ($type->direction() !== 1) {
            throw new InvalidArgumentException("Movement type [{$type->value}] does not add stock.");
        }

        if (bccomp($this->normalise($unitCost, self::COST_SCALE), '0', self::COST_SCALE) < 0) {
            throw new BusinessRuleException('The unit cost cannot be negative.');
        }

        return DB::transaction(fn (): StockMovement => $this->record(
            $item, $warehouse, $type, 1, $quantity, $unitCost, $reference, $notes, $movedAt,
        ));
    }

    /**
     * Remove stock at the current average cost (issue, sale, adjustment decrease, transfer out).
     *
     * @throws InsufficientStock when the warehouse holds less than the quantity
     */
    public function issue(
        Item $item,
        Warehouse $warehouse,
        string $quantity,
        StockMovementType $type = StockMovementType::Issue,
        ?Model $reference = null,
        ?string $notes = null,
        ?CarbonInterface $movedAt = null,
    ): StockMovement {
        if ($type->direction() !== -1) {
            throw new InvalidArgumentException("Movement type [{$type->value}] does not remove stock.");
        }

        return DB::transaction(fn (): StockMovement => $this->record(
            $item, $warehouse, $type, -1, $quantity, null, $reference, $notes, $movedAt,
        ));
    }

    /**
     * Move stock between warehouses. The receiving warehouse takes the stock at the sending warehouse's average cost.
     * The "transfer in" movement references the "transfer out" movement, so the pair can be reversed together.
     *
     * @return array{0: StockMovement, 1: StockMovement} [out, in]
     */
    public function transfer(
        Item $item,
        Warehouse $from,
        Warehouse $to,
        string $quantity,
        ?string $notes = null,
        ?CarbonInterface $movedAt = null,
    ): array {
        if ($from->is($to)) {
            throw new BusinessRuleException('Choose two different warehouses for a transfer.');
        }

        return DB::transaction(function () use ($item, $from, $to, $quantity, $notes, $movedAt): array {
            /*
             * Lock both balance rows in a fixed order so two opposite transfers cannot deadlock each other.
             */
            collect([$from, $to])->sortBy('id')->each(fn (Warehouse $warehouse) => $this->lockLevel($item, $warehouse));

            $out = $this->record($item, $from, StockMovementType::TransferOut, -1, $quantity, null, null, $notes, $movedAt);
            $in = $this->record($item, $to, StockMovementType::TransferIn, 1, $quantity, (string) $out->unit_cost, $out, $notes, $movedAt);

            return [$out, $in];
        });
    }

    /**
     * Record the starting quantity of an item in a warehouse. Only allowed before the item has any movement there.
     */
    public function recordOpeningBalance(Item $item, Warehouse $warehouse, string $quantity, string $unitCost, ?string $notes = null, ?CarbonInterface $movedAt = null): StockMovement
    {
        return DB::transaction(function () use ($item, $warehouse, $quantity, $unitCost, $notes, $movedAt): StockMovement {
            $this->lockLevel($item, $warehouse);

            $hasHistory = StockMovement::query()->where('item_id', $item->id)->where('warehouse_id', $warehouse->id)->exists();

            if ($hasHistory) {
                throw new BusinessRuleException("{$item->name} already has stock history in {$warehouse->name}. Use a stock adjustment instead.");
            }

            return $this->receive($item, $warehouse, $quantity, $unitCost, StockMovementType::OpeningBalance, null, $notes, $movedAt);
        });
    }

    /**
     * Undo a movement with an equal and opposite movement at the original unit cost.
     * Reversing a "transfer out" also reverses its "transfer in" (and vice versa).
     *
     * @return Collection<int, StockMovement> the reversing movements
     */
    public function reverse(StockMovement $movement, ?string $reason = null): Collection
    {
        return DB::transaction(function () use ($movement, $reason): Collection {
            $legs = match ($movement->type) {
                StockMovementType::TransferOut => collect([$this->transferInFor($movement), $movement]),
                StockMovementType::TransferIn => collect([$movement, $movement->reference]),
                default => collect([$movement]),
            };

            return $legs->filter()->map(fn (StockMovement $leg): StockMovement => $this->reverseOne($leg, $reason))->values();
        });
    }

    /**
     * Reverse every movement a document created that has not already been reversed (e.g. when an invoice or GRN is voided).
     *
     * @return Collection<int, StockMovement> the reversing movements
     */
    public function reverseFor(Model $reference, ?string $reason = null): Collection
    {
        return DB::transaction(function () use ($reference, $reason): Collection {
            return StockMovement::query()
                ->where('reference_type', $reference->getMorphClass())
                ->where('reference_id', $reference->getKey())
                ->whereNull('reverses_movement_id')
                ->whereDoesntHave('reversal')
                ->orderByDesc('id')
                ->get()
                ->map(fn (StockMovement $movement): StockMovement => $this->reverseOne($movement, $reason));
        });
    }

    /**
     * The quantity of an item available in a warehouse.
     */
    public function available(Item $item, Warehouse $warehouse): string
    {
        $quantity = StockLevel::query()->where('item_id', $item->id)->where('warehouse_id', $warehouse->id)->value('quantity');

        return $this->normalise((string) ($quantity ?? '0'), self::QUANTITY_SCALE);
    }

    /**
     * Whether a movement may be reversed: it is not a reversal itself and has not been reversed already.
     */
    public function isReversible(StockMovement $movement): bool
    {
        return ! $movement->isReversal() && ! $movement->reversal()->exists();
    }

    private function reverseOne(StockMovement $movement, ?string $reason): StockMovement
    {
        $movement = StockMovement::query()->lockForUpdate()->findOrFail($movement->id);

        if ($movement->isReversal()) {
            throw new BusinessRuleException('A reversal cannot itself be reversed.');
        }

        if ($movement->reversal()->exists()) {
            throw new BusinessRuleException('This stock movement has already been reversed.');
        }

        return $this->record(
            $movement->item,
            $movement->warehouse,
            StockMovementType::Reversal,
            -$movement->direction,
            (string) $movement->quantity,
            (string) $movement->unit_cost,
            $movement->reference,
            $reason ?? "Reversal of {$movement->type->label()} #{$movement->id}",
            null,
            $movement,
        );
    }

    /**
     * Write one movement and update the balance. Must run inside a transaction.
     *
     * @param  string|null  $unitCost  the cost of stock coming in, or of stock removed by a reversal; null means the current average
     */
    private function record(
        Item $item,
        Warehouse $warehouse,
        StockMovementType $type,
        int $direction,
        string $quantity,
        ?string $unitCost,
        ?Model $reference,
        ?string $notes,
        ?CarbonInterface $movedAt,
        ?StockMovement $reverses = null,
    ): StockMovement {
        $quantity = $this->normalise($quantity, self::QUANTITY_SCALE);

        if (bccomp($quantity, '0', self::QUANTITY_SCALE) <= 0) {
            throw new BusinessRuleException('The quantity must be greater than zero.');
        }

        if (! $item->isStocked()) {
            throw new BusinessRuleException("{$item->name} is a service and does not carry stock.");
        }

        if ($reverses === null && ! $item->is_active) {
            throw new BusinessRuleException("{$item->name} is inactive. Reactivate it before moving stock.");
        }

        if ($reverses === null && ! $warehouse->is_active) {
            throw new BusinessRuleException("{$warehouse->name} is inactive. Reactivate it before moving stock.");
        }

        $level = $this->lockLevel($item, $warehouse);
        $currentQuantity = $this->normalise((string) $level->quantity, self::QUANTITY_SCALE);
        $currentCost = $this->normalise((string) $level->average_cost, self::COST_SCALE);

        if ($direction === 1) {
            $cost = $this->normalise($unitCost ?? $currentCost, self::COST_SCALE);
            $newQuantity = bcadd($currentQuantity, $quantity, self::QUANTITY_SCALE);
            $newCost = $this->round(bcdiv(
                bcadd(bcmul($currentQuantity, $currentCost, self::WORKING_SCALE), bcmul($quantity, $cost, self::WORKING_SCALE), self::WORKING_SCALE),
                $newQuantity,
                self::WORKING_SCALE,
            ), self::COST_SCALE);
        } else {
            $newQuantity = bcsub($currentQuantity, $quantity, self::QUANTITY_SCALE);

            if (bccomp($newQuantity, '0', self::QUANTITY_SCALE) < 0) {
                throw InsufficientStock::for($item->name, $warehouse->name, $currentQuantity, $quantity);
            }

            if ($unitCost === null) {
                $cost = $currentCost;
                $newCost = $currentCost;
            } else {
                /*
                 * A reversal of incoming stock takes that stock back out at the cost it came in at.
                 */
                $cost = $this->normalise($unitCost, self::COST_SCALE);
                $newCost = bccomp($newQuantity, '0', self::QUANTITY_SCALE) === 0
                    ? $currentCost
                    : $this->round(bcdiv(
                        bcsub(bcmul($currentQuantity, $currentCost, self::WORKING_SCALE), bcmul($quantity, $cost, self::WORKING_SCALE), self::WORKING_SCALE),
                        $newQuantity,
                        self::WORKING_SCALE,
                    ), self::COST_SCALE);

                if (bccomp($newCost, '0', self::COST_SCALE) < 0) {
                    $newCost = '0.0000';
                }
            }
        }

        $level->forceFill(['quantity' => $newQuantity, 'average_cost' => $newCost])->save();

        $movement = new StockMovement;
        $movement->forceFill([
            'item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'type' => $type,
            'direction' => $direction,
            'quantity' => $quantity,
            'unit_cost' => $cost,
            'balance_after' => $newQuantity,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'reverses_movement_id' => $reverses?->id,
            'moved_at' => $movedAt ?? now(),
            'notes' => $notes !== null ? mb_substr($notes, 0, 500) : null,
            'created_by' => Auth::id(),
        ])->save();

        if ($direction === -1) {
            $this->lowStockNotifier->checkAfterMovement($item, $quantity);
        }

        return $movement;
    }

    /**
     * The balance row for an item in a warehouse, created at zero if missing, locked until the transaction ends.
     */
    private function lockLevel(Item $item, Warehouse $warehouse): StockLevel
    {
        StockLevel::query()->insertOrIgnore([
            'item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 0,
            'average_cost' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return StockLevel::query()
            ->where('item_id', $item->id)
            ->where('warehouse_id', $warehouse->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function transferInFor(StockMovement $transferOut): ?StockMovement
    {
        return StockMovement::query()
            ->where('type', StockMovementType::TransferIn->value)
            ->where('reference_type', $transferOut->getMorphClass())
            ->where('reference_id', $transferOut->id)
            ->first();
    }

    /**
     * Validate a numeric string and bring it to the given scale.
     */
    private function normalise(string $value, int $scale): string
    {
        $value = trim($value);

        if (! preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            throw new InvalidArgumentException("[{$value}] is not a valid number.");
        }

        return $this->round($value, $scale);
    }

    /**
     * Round half away from zero to the given scale (bcmath itself truncates).
     */
    private function round(string $value, int $scale): string
    {
        $half = '0.'.str_repeat('0', $scale).'5';

        return str_starts_with($value, '-')
            ? bcsub($value, $half, $scale)
            : bcadd($value, $half, $scale);
    }
}
