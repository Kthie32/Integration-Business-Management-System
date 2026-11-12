<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Delivering approved requisition lines. Lines are keyed by requisition line id; empty quantities are
 * skipped. Business limits (approved quantities, stock on hand) are checked by the fulfilment service.
 */
class FulfilRequisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fulfil', $this->route('requisition'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')],
            'expense_category_id' => ['nullable', 'integer', Rule::exists('expense_categories', 'id')],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'notes' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array'],
            'lines.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:99999999', 'decimal:0,3'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999999', 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fulfilmentData(): array
    {
        return $this->validated();
    }
}
