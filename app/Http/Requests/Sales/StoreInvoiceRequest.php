<?php

namespace App\Http\Requests\Sales;

use App\Enums\DiscountType;
use App\Models\Invoice;
use App\Models\Item;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Used for both creating and updating a draft invoice. Totals are always recalculated on the server.
 */
class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('invoice');

        return $invoice instanceof Invoice
            ? $this->user()->can('update', $invoice)
            : $this->user()->can('create', Invoice::class);
    }

    /**
     * Blank rows left in the line editor are ignored; a catalogue line without a description takes the item's name.
     */
    protected function prepareForValidation(): void
    {
        $lines = collect((array) $this->input('lines', []))
            ->filter(fn ($line): bool => is_array($line)
                && (filled($line['item_id'] ?? null) || filled($line['description'] ?? null) || filled($line['quantity'] ?? null)))
            ->values();

        $names = Item::query()->whereIn('id', $lines->pluck('item_id')->filter()->map(fn ($id): int => (int) $id))->pluck('name', 'id');

        $this->merge([
            'lines' => $lines->map(function (array $line) use ($names): array {
                if (blank($line['description'] ?? null) && filled($line['item_id'] ?? null)) {
                    $line['description'] = $names->get((int) $line['item_id']);
                }

                return $line;
            })->all(),
            'document_discount_type' => filled($this->input('document_discount_type')) ? $this->input('document_discount_type') : null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'document_discount_type' => ['nullable', Rule::enum(DiscountType::class)],
            'document_discount_value' => [
                'nullable', 'required_with:document_discount_type', 'numeric', 'min:0', 'decimal:0,4',
                $this->input('document_discount_type') === DiscountType::Percentage->value ? 'max:100' : 'max:9999999999999',
            ],
            'notes' => ['nullable', 'string', 'max:5000'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.item_id' => ['nullable', 'integer', Rule::exists('items', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'lines.*.description' => ['required', 'string', 'max:500'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999999', 'decimal:0,3'],
            'lines.*.unit' => ['nullable', 'string', 'max:20'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0', 'max:99999999999', 'decimal:0,4'],
            'lines.*.discount_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999', 'decimal:0,2'],
            'lines.*.tax_rate_id' => ['nullable', 'integer', Rule::exists('tax_rates', 'id')->where('is_active', true)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lines.required' => 'Add at least one line.',
            'lines.*.description.required' => 'Describe the item or service.',
            'lines.*.item_id.exists' => 'Choose an active item.',
            'lines.*.tax_rate_id.exists' => 'Choose an active tax rate.',
            'warehouse_id.exists' => 'Choose an active warehouse.',
            'due_date.after_or_equal' => 'The due date cannot be before the invoice date.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function invoiceData(): array
    {
        $data = $this->validated();
        $data['customer_id'] = (int) $data['customer_id'];
        $data['warehouse_id'] = filled($data['warehouse_id'] ?? null) ? (int) $data['warehouse_id'] : null;
        $data['due_date'] = $data['due_date'] ?? null;
        $data['lines'] = array_map(fn (array $line): array => [
            'item_id' => filled($line['item_id'] ?? null) ? (int) $line['item_id'] : null,
            'description' => (string) $line['description'],
            'quantity' => (string) $line['quantity'],
            'unit' => $line['unit'] ?? null,
            'unit_price' => (string) $line['unit_price'],
            'discount_amount' => filled($line['discount_amount'] ?? null) ? (string) $line['discount_amount'] : '0',
            'tax_rate_id' => filled($line['tax_rate_id'] ?? null) ? (int) $line['tax_rate_id'] : null,
        ], $data['lines']);

        return $data;
    }
}
