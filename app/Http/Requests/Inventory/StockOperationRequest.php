<?php

namespace App\Http\Requests\Inventory;

use App\Enums\ItemType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a direct stock operation: issue, transfer or opening balance. The route name decides which.
 */
class StockOperationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match ($this->operation()) {
            'issue' => $this->user()->can('stock.issue'),
            'transfer' => $this->user()->can('stock.transfer'),
            'opening' => $this->user()->can('stock.adjust'),
        };
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $activeWarehouse = Rule::exists('warehouses', 'id')->where('is_active', true);

        $rules = [
            'item_id' => ['required', 'integer', Rule::exists('items', 'id')->where('type', ItemType::Stock->value)->where('is_active', true)->whereNull('deleted_at')],
            'warehouse_id' => ['required', 'integer', $activeWarehouse],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999999', 'decimal:0,3'],
            'moved_at' => ['required', 'date', 'before_or_equal:today'],
            'notes' => [$this->operation() === 'issue' ? 'required' : 'nullable', 'string', 'max:500'],
        ];

        if ($this->operation() === 'transfer') {
            $rules['to_warehouse_id'] = ['required', 'integer', 'different:warehouse_id', $activeWarehouse];
        }

        if ($this->operation() === 'opening') {
            $rules['unit_cost'] = ['required', 'numeric', 'min:0', 'max:99999999999', 'decimal:0,4'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'warehouse_id' => $this->operation() === 'transfer' ? 'from warehouse' : 'warehouse',
            'to_warehouse_id' => 'to warehouse',
            'notes' => $this->operation() === 'issue' ? 'purpose' : 'notes',
        ];
    }

    /**
     * "issue", "transfer" or "opening".
     */
    public function operation(): string
    {
        return (string) $this->route('operation');
    }
}
