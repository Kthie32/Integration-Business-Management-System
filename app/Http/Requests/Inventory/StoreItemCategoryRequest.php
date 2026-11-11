<?php

namespace App\Http\Requests\Inventory;

use App\Models\ItemCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Used for both creating and updating an item category.
 */
class StoreItemCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('item_category');

        return $category instanceof ItemCategory
            ? $this->user()->can('update', $category)
            : $this->user()->can('create', ItemCategory::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'parent_id' => ['nullable', 'integer', Rule::exists('item_categories', 'id')->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
