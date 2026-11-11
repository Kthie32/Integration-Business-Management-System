<?php

namespace App\Services\Inventory;

use App\Enums\DocumentType;
use App\Enums\GoodsReceiptStatus;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\User;
use App\Services\System\NumberingService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Goods received notes: drafted, then posted into stock (taking a GRN number), and voided by reversing that stock.
 */
class GoodsReceiptService
{
    public function __construct(private NumberingService $numbering, private StockService $stock) {}

    /**
     * @param  array{supplier_id: int, warehouse_id: int, received_at: string, supplier_invoice_ref?: string|null, notes?: string|null, lines: list<array{item_id: int, quantity: string, unit_cost: string}>}  $data
     */
    public function createDraft(array $data, User $actor): GoodsReceipt
    {
        return DB::transaction(function () use ($data, $actor): GoodsReceipt {
            $receipt = new GoodsReceipt($data);
            $receipt->forceFill(['status' => GoodsReceiptStatus::Draft, 'created_by' => $actor->id])->save();
            $receipt->recordStatusChange(null, GoodsReceiptStatus::Draft);

            $this->replaceLines($receipt, $data['lines']);

            return $receipt;
        });
    }

    /**
     * @param  array{supplier_id: int, warehouse_id: int, received_at: string, supplier_invoice_ref?: string|null, notes?: string|null, lines: list<array{item_id: int, quantity: string, unit_cost: string}>}  $data
     */
    public function updateDraft(GoodsReceipt $receipt, array $data): GoodsReceipt
    {
        $this->ensureDraft($receipt);

        return DB::transaction(function () use ($receipt, $data): GoodsReceipt {
            $receipt->update($data);
            $this->replaceLines($receipt, $data['lines']);

            return $receipt;
        });
    }

    public function deleteDraft(GoodsReceipt $receipt): void
    {
        $this->ensureDraft($receipt);

        $receipt->delete();
    }

    /**
     * Add every line to stock and give the note its number. All lines post together or none do.
     */
    public function post(GoodsReceipt $receipt, User $actor): GoodsReceipt
    {
        return DB::transaction(function () use ($receipt, $actor): GoodsReceipt {
            $receipt = GoodsReceipt::query()->lockForUpdate()->findOrFail($receipt->id);
            $this->ensureDraft($receipt);
            $receipt->load('items.item', 'warehouse', 'supplier');

            if ($receipt->items->isEmpty()) {
                throw new BusinessRuleException('Add at least one item before posting.');
            }

            foreach ($receipt->items as $line) {
                $this->stock->receive(
                    $line->item,
                    $receipt->warehouse,
                    (string) $line->quantity,
                    (string) $line->unit_cost,
                    StockMovementType::GoodsReceived,
                    $receipt,
                    "Received from {$receipt->supplier->name}",
                    $receipt->received_at->copy()->setTimeFrom(now()),
                );
            }

            $receipt->forceFill([
                'number' => $this->numbering->next(DocumentType::GoodsReceipt, $receipt->received_at),
                'posted_at' => now(),
                'posted_by' => $actor->id,
            ]);
            $receipt->transitionTo(GoodsReceiptStatus::Posted);

            return $receipt;
        });
    }

    /**
     * Reverse the stock a posted note added. Refused if some of that stock has already been issued.
     */
    public function void(GoodsReceipt $receipt, string $reason, User $actor): GoodsReceipt
    {
        return DB::transaction(function () use ($receipt, $reason, $actor): GoodsReceipt {
            $receipt = GoodsReceipt::query()->lockForUpdate()->findOrFail($receipt->id);

            if ($receipt->status !== GoodsReceiptStatus::Posted) {
                throw new BusinessRuleException('Only a posted goods received note can be voided.');
            }

            $this->stock->reverseFor($receipt, "Void of {$receipt->number}: {$reason}");

            $receipt->forceFill(['voided_at' => now(), 'voided_by' => $actor->id, 'void_reason' => $reason]);
            $receipt->transitionTo(GoodsReceiptStatus::Void, $reason);

            return $receipt;
        });
    }

    /**
     * @param  list<array{item_id: int, quantity: string, unit_cost: string}>  $lines
     */
    private function replaceLines(GoodsReceipt $receipt, array $lines): void
    {
        $items = Item::query()->whereIn('id', array_column($lines, 'item_id'))->get()->keyBy('id');

        $receipt->items()->delete();
        $total = Money::zero();

        foreach ($lines as $line) {
            $item = $items->get($line['item_id']);

            if ($item === null || ! $item->isStocked()) {
                throw new BusinessRuleException('Only stock items can be received.');
            }

            $lineTotal = Money::of('0')->plus(bcmul((string) $line['quantity'], (string) $line['unit_cost'], 6));

            $receipt->items()->create([
                'item_id' => $item->id,
                'quantity' => $line['quantity'],
                'unit_cost' => $line['unit_cost'],
                'line_total' => (string) $lineTotal,
            ]);

            $total = $total->plus($lineTotal);
        }

        $receipt->forceFill(['total' => (string) $total])->save();
    }

    private function ensureDraft(GoodsReceipt $receipt): void
    {
        if (! $receipt->isDraft()) {
            throw new BusinessRuleException('Only a draft goods received note can be changed.');
        }
    }
}
