<?php

namespace App\Services\Requisition;

use App\Enums\FulfilmentType;
use App\Enums\RequisitionStatus;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Requisition;
use App\Models\RequisitionFulfilment;
use App\Models\RequisitionItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\SystemMessage;
use App\Services\Finance\ExpenseService;
use App\Services\Inventory\StockService;
use App\Support\Money;
use App\Support\Quantity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Fulfilment of approved requisition lines (FR-REQ-06), all in one transaction (FR-GEN-08):
 * catalogue stock items are issued from a warehouse through StockService; everything else is purchased and
 * recorded as one draft expense linked to the requisition, which Finance then records. Quantities can never
 * exceed what was approved.
 */
class RequisitionFulfilmentService
{
    public function __construct(private StockService $stock, private ExpenseService $expenses) {}

    /**
     * @param  array{warehouse_id?: int|string|null, expense_category_id?: int|string|null, supplier_id?: int|string|null, notes?: string|null, lines: array<int|string, array{quantity?: string|null, unit_cost?: string|null}>}  $data
     */
    public function fulfil(Requisition $requisition, array $data, User $actor): Requisition
    {
        $result = DB::transaction(function () use ($requisition, $data, $actor): array {
            $requisition = Requisition::query()->lockForUpdate()->with('items.item', 'department')->findOrFail($requisition->id);

            if (! $requisition->status->isFulfillable()) {
                throw new BusinessRuleException("Requisition {$requisition->displayNumber()} is {$requisition->status->label()}; only approved requisitions can be fulfilled.");
            }

            $lines = $this->collectLines($requisition, $data['lines'] ?? []);
            $notes = filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null;
            $stockLines = array_filter($lines, fn (array $line): bool => $line['type'] === FulfilmentType::StockIssue);
            $purchaseLines = array_filter($lines, fn (array $line): bool => $line['type'] === FulfilmentType::Purchase);

            foreach ($stockLines as $line) {
                $this->issueFromStock($requisition, $line['item'], $line['quantity'], $this->warehouse($data['warehouse_id'] ?? null), $actor, $notes);
            }

            $expense = $purchaseLines === [] ? null : $this->purchase($requisition, $purchaseLines, $data, $actor, $notes);

            if ($requisition->fresh('items')->isCompletelyFulfilled()) {
                $requisition->forceFill(['fulfilled_at' => now()]);
                $requisition->transitionTo(RequisitionStatus::Fulfilled, $notes);
            } elseif ($requisition->status === RequisitionStatus::Approved) {
                $requisition->transitionTo(RequisitionStatus::PartiallyFulfilled, $notes);
            }

            return [$requisition, $expense];
        });

        [$requisition, $expense] = $result;

        $requisition->requester?->notify(new SystemMessage(
            "Requisition {$requisition->number} ".($requisition->status === RequisitionStatus::Fulfilled ? 'fulfilled' : 'partly fulfilled'),
            "{$actor->name} delivered items against your requisition.",
            route('requisitions.show', $requisition),
            'box-seam',
        ));

        if ($expense !== null) {
            Notification::send(
                User::query()->active()->permission('expenses.create')->whereKeyNot($actor->id)->get(),
                new SystemMessage(
                    'Draft expense from requisition '.$requisition->number,
                    'Purchases worth '.Money::of((string) $expense->amount)->format(2).' are waiting to be recorded.',
                    route('expenses.show', $expense),
                    'wallet',
                ),
            );
        }

        return $requisition;
    }

    /**
     * @param  array<int|string, array{quantity?: string|null, unit_cost?: string|null}>  $input
     * @return list<array{item: RequisitionItem, type: FulfilmentType, quantity: string, unit_cost: string|null}>
     */
    private function collectLines(Requisition $requisition, array $input): array
    {
        $lines = [];

        foreach ($input as $lineId => $values) {
            if (blank($values['quantity'] ?? null)) {
                continue;
            }

            $quantity = bcadd((string) $values['quantity'], '0', 3);

            if (bccomp($quantity, '0', 3) === 0) {
                continue;
            }

            $item = $requisition->items->firstWhere('id', (int) $lineId);

            if ($item === null) {
                throw new BusinessRuleException('A line does not belong to this requisition.');
            }

            if (bccomp($quantity, '0', 3) < 0) {
                throw new BusinessRuleException("The quantity for \"{$item->description}\" cannot be negative.");
            }

            if (bccomp($quantity, $item->remainingQuantity(), 3) > 0) {
                throw new BusinessRuleException('At most '.Quantity::format($item->remainingQuantity())." of \"{$item->description}\" can still be delivered.");
            }

            $lines[] = [
                'item' => $item,
                'type' => $item->fulfilmentType(),
                'quantity' => $quantity,
                'unit_cost' => filled($values['unit_cost'] ?? null) ? (string) $values['unit_cost'] : null,
            ];
        }

        if ($lines === []) {
            throw new BusinessRuleException('Enter the quantity delivered for at least one line.');
        }

        return $lines;
    }

    private function issueFromStock(Requisition $requisition, RequisitionItem $line, string $quantity, Warehouse $warehouse, User $actor, ?string $notes): void
    {
        $fulfilment = $this->newFulfilment($requisition, $line, FulfilmentType::StockIssue, $quantity, $actor, $notes);
        $fulfilment->forceFill(['warehouse_id' => $warehouse->id])->save();

        $movement = $this->stock->issue(
            $line->item,
            $warehouse,
            $quantity,
            StockMovementType::Issue,
            $fulfilment,
            "{$requisition->number}: {$line->description}",
        );

        $fulfilment->forceFill([
            'stock_movement_id' => $movement->id,
            'unit_cost' => $movement->unit_cost,
            'amount' => Money::of(bcmul((string) $movement->unit_cost, $quantity, 7))->toDecimal(),
        ])->save();

        $this->addDelivered($line, $quantity);
    }

    /**
     * @param  list<array{item: RequisitionItem, type: FulfilmentType, quantity: string, unit_cost: string|null}>  $lines
     * @param  array<string, mixed>  $data
     */
    private function purchase(Requisition $requisition, array $lines, array $data, User $actor, ?string $notes): Expense
    {
        $category = filled($data['expense_category_id'] ?? null) ? ExpenseCategory::query()->find((int) $data['expense_category_id']) : null;

        if ($category === null || ! $category->is_active) {
            throw new BusinessRuleException('Choose an active expense category for the purchased lines.');
        }

        $costed = [];

        foreach ($lines as $line) {
            $unitCost = Money::of($line['unit_cost'] ?? (string) $line['item']->estimated_unit_cost);

            if ($unitCost->isNegative()) {
                throw new BusinessRuleException('Unit costs cannot be negative.');
            }

            $costed[] = [...$line, 'unit_cost' => $unitCost, 'amount' => $unitCost->multipliedBy($line['quantity'])];
        }

        $total = Money::sum(array_column($costed, 'amount'));

        if (! $total->isPositive()) {
            throw new BusinessRuleException('Enter what the purchased lines cost.');
        }

        $expense = $this->expenses->createDraft([
            'expense_date' => today()->toDateString(),
            'expense_category_id' => $category->id,
            'supplier_id' => filled($data['supplier_id'] ?? null) ? (int) $data['supplier_id'] : null,
            'department_id' => $requisition->department_id,
            'description' => "{$requisition->number}: ".implode('; ', array_map(
                fn (array $line): string => $line['item']->description.' × '.Quantity::format($line['quantity']),
                $costed,
            )),
            'amount' => $total->toDecimal(),
            'tax_amount' => '0',
            'notes' => $notes,
        ], null, $actor);

        $expense->source()->associate($requisition);
        $expense->save();

        foreach ($costed as $line) {
            $fulfilment = $this->newFulfilment($requisition, $line['item'], FulfilmentType::Purchase, $line['quantity'], $actor, $notes);
            $fulfilment->forceFill([
                'expense_id' => $expense->id,
                'unit_cost' => $line['unit_cost']->toDecimal(),
                'amount' => $line['amount']->toDecimal(),
            ])->save();

            $this->addDelivered($line['item'], $line['quantity']);
        }

        return $expense;
    }

    private function newFulfilment(Requisition $requisition, RequisitionItem $line, FulfilmentType $type, string $quantity, User $actor, ?string $notes): RequisitionFulfilment
    {
        $fulfilment = new RequisitionFulfilment;
        $fulfilment->forceFill([
            'requisition_id' => $requisition->id,
            'requisition_item_id' => $line->id,
            'fulfilment_type' => $type,
            'quantity' => $quantity,
            'fulfilled_by' => $actor->id,
            'fulfilled_at' => now(),
            'notes' => $notes,
        ])->save();

        return $fulfilment;
    }

    private function addDelivered(RequisitionItem $line, string $quantity): void
    {
        $line->forceFill(['fulfilled_quantity' => bcadd((string) $line->fulfilled_quantity, $quantity, 3)])->save();
    }

    private function warehouse(int|string|null $warehouseId): Warehouse
    {
        $warehouse = filled($warehouseId) ? Warehouse::query()->find((int) $warehouseId) : null;

        if ($warehouse === null || ! $warehouse->is_active) {
            throw new BusinessRuleException('Choose an active warehouse to issue the stock items from.');
        }

        return $warehouse;
    }
}
