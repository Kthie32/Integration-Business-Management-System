<?php

namespace App\Http\Requests\Inventory;

use App\Enums\ItemType;
use App\Models\Item;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Used for both creating and updating an item.
 */
class StoreItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $item = $this->route('item');

        return $item instanceof Item
            ? $this->user()->can('update', $item)
            : $this->user()->can('create', Item::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'min_stock_level' => filled($this->input('min_stock_level')) ? $this->input('min_stock_level') : '0',
            'cost_price' => filled($this->input('cost_price')) ? $this->input('cost_price') : '0',
            'selling_price' => filled($this->input('selling_price')) ? $this->input('selling_price') : '0',
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $amount = ['required', 'numeric', 'min:0', 'max:99999999999', 'decimal:0,4'];
        $quantity = ['numeric', 'min:0', 'max:999999999999', 'decimal:0,3'];

        return [
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'item_category_id' => ['nullable', 'integer', Rule::exists('item_categories', 'id')->whereNull('deleted_at')],
            'type' => ['required', Rule::enum(ItemType::class)],
            'unit' => ['required', 'string', 'max:20'],
            'selling_price' => $amount,
            'cost_price' => $amount,
            'tax_rate_id' => ['nullable', 'integer', Rule::exists('tax_rates', 'id')],
            'min_stock_level' => ['required', ...$quantity],
            'reorder_qty' => ['nullable', ...$quantity],
            'is_active' => ['boolean'],
        ];
    }
}
