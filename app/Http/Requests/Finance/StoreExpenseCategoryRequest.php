<?php

namespace App\Http\Requests\Finance;

use App\Models\ExpenseCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Used for both creating and updating an expense category.
 */
class StoreExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('expense_category');

        return $category instanceof ExpenseCategory
            ? $this->user()->can('update', $category)
            : $this->user()->can('create', ExpenseCategory::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => filled($this->input('code')) ? strtoupper(trim((string) $this->input('code'))) : null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $category = $this->route('expense_category');
        $ignore = $category instanceof ExpenseCategory ? $category->id : null;

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('expense_categories', 'name')->ignore($ignore)],
            'code' => ['nullable', 'string', 'max:20', 'alpha_dash', Rule::unique('expense_categories', 'code')->ignore($ignore)],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }
}
