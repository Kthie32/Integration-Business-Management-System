<?php

namespace App\Http\Requests\Inventory;

use App\Enums\ItemType;
use App\Models\StockAdjustment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', StockAdjustment::class);
    }

    /**
     * Blank rows left in the line editor are ignored.
     */
    protected function prepareForValidation(): void
    {
        $lines = collect((array) $this->input('lines', []))
            ->filter(fn ($line): bool => is_array($line) && (filled($line['item_id'] ?? null) || filled($line['quantity_change'] ?? null)))
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
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'adjusted_at' => ['required', 'date', 'before_or_equal:today'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.item_id' => ['required', 'integer', 'distinct', Rule::exists('items', 'id')->where('type', ItemType::Stock->value)->whereNull('deleted_at')],
            'lines.*.quantity_change' => ['required', 'numeric', 'not_in:0', 'min:-999999999999', 'max:999999999999', 'decimal:0,3'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999999', 'decimal:0,4'],
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
            'lines.*.quantity_change.not_in' => 'The change cannot be zero.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function adjustmentData(): array
    {
        $data = $this->validated();
        $data['lines'] = array_map(fn (array $line): array => [
            'item_id' => (int) $line['item_id'],
            'quantity_change' => (string) $line['quantity_change'],
            'unit_cost' => isset($line['unit_cost']) ? (string) $line['unit_cost'] : null,
        ], $data['lines']);

        return $data;
    }
}
