<?php

namespace App\Http\Requests\Inventory;

use App\Enums\ItemType;
use App\Models\GoodsReceipt;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Used for both creating and updating a draft goods received note.
 */
class StoreGoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        $receipt = $this->route('goods_receipt');

        return $receipt instanceof GoodsReceipt
            ? $this->user()->can('update', $receipt)
            : $this->user()->can('create', GoodsReceipt::class);
    }

    /**
     * Blank rows left in the line editor are ignored.
     */
    protected function prepareForValidation(): void
    {
        $lines = collect((array) $this->input('lines', []))
            ->filter(fn ($line): bool => is_array($line) && (filled($line['item_id'] ?? null) || filled($line['quantity'] ?? null)))
            ->values()
            ->all();

        $this->merge(['lines' => $lines]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'received_at' => ['required', 'date', 'before_or_equal:today'],
            'supplier_invoice_ref' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.item_id' => ['required', 'integer', 'distinct', Rule::exists('items', 'id')->where('type', ItemType::Stock->value)->where('is_active', true)->whereNull('deleted_at')],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999999', 'decimal:0,3'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:99999999999', 'decimal:0,4'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lines.required' => 'Add at least one item.',
            'lines.*.item_id.distinct' => 'Each item can only appear once.',
            'lines.*.item_id.exists' => 'Choose an active stock item.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function receiptData(): array
    {
        $data = $this->validated();
        $data['lines'] = array_map(fn (array $line): array => [
            'item_id' => (int) $line['item_id'],
            'quantity' => (string) $line['quantity'],
            'unit_cost' => (string) $line['unit_cost'],
        ], $data['lines']);

        return $data;
    }
}
