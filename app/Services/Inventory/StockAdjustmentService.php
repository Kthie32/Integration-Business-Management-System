<?php

namespace App\Services\Inventory;

use App\Enums\DocumentType;
use App\Enums\StockAdjustmentStatus;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\Item;
use App\Models\StockAdjustment;
use App\Models\StockLevel;
use App\Models\User;
use App\Notifications\SystemMessage;
use App\Services\System\NumberingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Stock adjustments: raised by someone with stock.adjust, then approved (which changes stock) or rejected by a
 * different person holding stock.adjust_approve (docs/07 C3).
 */
class StockAdjustmentService
{
    public function __construct(private NumberingService $numbering, private StockService $stock) {}

    /**
     * @param  array{warehouse_id: int, adjusted_at: string, reason: string, lines: list<array{item_id: int, quantity_change: string, unit_cost?: string|null}>}  $data
     */
    public function create(array $data, User $actor): StockAdjustment
    {
        $adjustment = DB::transaction(function () use ($data, $actor): StockAdjustment {
            $items = Item::query()->whereIn('id', array_column($data['lines'], 'item_id'))->get()->keyBy('id');

            $adjustment = new StockAdjustment($data);
            $adjustment->forceFill([
                'number' => $this->numbering->next(DocumentType::StockAdjustment, now()),
                'status' => StockAdjustmentStatus::Pending,
                'created_by' => $actor->id,
            ])->save();
            $adjustment->recordStatusChange(null, StockAdjustmentStatus::Pending);

            foreach ($data['lines'] as $line) {
                $item = $items->get($line['item_id']);

                if ($item === null || ! $item->isStocked()) {
                    throw new BusinessRuleException('Only stock items can be adjusted.');
                }

                if (bccomp((string) $line['quantity_change'], '0', 3) === 0) {
                    throw new BusinessRuleException("The change for {$item->name} cannot be zero.");
                }

                $adjustment->items()->create([
                    'item_id' => $item->id,
                    'quantity_change' => $line['quantity_change'],
                    'unit_cost' => bccomp((string) $line['quantity_change'], '0', 3) > 0 && filled($line['unit_cost'] ?? null) ? $line['unit_cost'] : null,
                ]);
            }

            return $adjustment;
        });

        $approvers = User::query()->active()->permission('stock.adjust_approve')->whereKeyNot($actor->id)->get();
        Notification::send($approvers, new SystemMessage(
            "Stock adjustment {$adjustment->number} awaits approval",
            "{$actor->name} raised a stock adjustment: {$adjustment->reason}",
            route('stock-adjustments.show', $adjustment),
            'clipboard-check',
        ));

        return $adjustment;
    }

    /**
     * Apply every line to stock. Increases come in at the line's unit cost, or the current average cost when empty.
     */
    public function approve(StockAdjustment $adjustment, User $actor, ?string $comment = null): StockAdjustment
    {
        return DB::transaction(function () use ($adjustment, $actor, $comment): StockAdjustment {
            $adjustment = $this->lockPending($adjustment, $actor);
            $adjustment->load('items.item', 'warehouse');

            foreach ($adjustment->items as $line) {
                $change = (string) $line->quantity_change;
                $notes = "{$adjustment->number}: {$adjustment->reason}";

                if ($line->isIncrease()) {
                    $unitCost = $line->unit_cost !== null
                        ? (string) $line->unit_cost
                        : $this->currentCost($line->item, $adjustment->warehouse_id);

                    $this->stock->receive($line->item, $adjustment->warehouse, $change, $unitCost, StockMovementType::AdjustmentIn, $adjustment, $notes);
                } else {
                    $this->stock->issue($line->item, $adjustment->warehouse, ltrim($change, '-'), StockMovementType::AdjustmentOut, $adjustment, $notes);
                }
            }

            $adjustment->forceFill(['approved_by' => $actor->id, 'approved_at' => now()]);
            $adjustment->transitionTo(StockAdjustmentStatus::Approved, $comment);

            $adjustment->creator?->notify(new SystemMessage(
                "Stock adjustment {$adjustment->number} approved",
                "{$actor->name} approved your stock adjustment.",
                route('stock-adjustments.show', $adjustment),
                'check-circle',
            ));

            return $adjustment;
        });
    }

    public function reject(StockAdjustment $adjustment, User $actor, string $comment): StockAdjustment
    {
        return DB::transaction(function () use ($adjustment, $actor, $comment): StockAdjustment {
            $adjustment = $this->lockPending($adjustment, $actor);
            $adjustment->transitionTo(StockAdjustmentStatus::Rejected, $comment);

            $adjustment->creator?->notify(new SystemMessage(
                "Stock adjustment {$adjustment->number} rejected",
                "{$actor->name}: {$comment}",
                route('stock-adjustments.show', $adjustment),
                'x-circle',
            ));

            return $adjustment;
        });
    }

    /**
     * The creator withdraws an adjustment that has not been decided yet.
     */
    public function cancel(StockAdjustment $adjustment, User $actor): StockAdjustment
    {
        return DB::transaction(function () use ($adjustment, $actor): StockAdjustment {
            $adjustment = StockAdjustment::query()->lockForUpdate()->findOrFail($adjustment->id);

            if ($adjustment->created_by !== $actor->id) {
                throw new BusinessRuleException('Only the person who raised an adjustment can cancel it.');
            }

            $adjustment->transitionTo(StockAdjustmentStatus::Cancelled, 'Cancelled by creator');

            return $adjustment;
        });
    }

    private function lockPending(StockAdjustment $adjustment, User $actor): StockAdjustment
    {
        $adjustment = StockAdjustment::query()->lockForUpdate()->findOrFail($adjustment->id);

        if (! $adjustment->isPending()) {
            throw new BusinessRuleException('This adjustment has already been decided.');
        }

        if ($adjustment->created_by === $actor->id) {
            throw new BusinessRuleException('You cannot approve or reject an adjustment you raised.');
        }

        return $adjustment;
    }

    private function currentCost(Item $item, int $warehouseId): string
    {
        $average = StockLevel::query()->where('item_id', $item->id)->where('warehouse_id', $warehouseId)->value('average_cost');

        return bccomp((string) ($average ?? '0'), '0', 4) > 0 ? (string) $average : (string) $item->cost_price;
    }
}
