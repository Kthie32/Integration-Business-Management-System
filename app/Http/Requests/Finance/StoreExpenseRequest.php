<?php

namespace App\Http\Requests\Finance;

use App\Enums\PaymentMethod;
use App\Models\Expense;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creating or editing a draft expense (FR-EXP-01). Receipts/invoices from the payee may be attached as a
 * PDF, JPG or PNG up to 5 MB; both the extension and the detected content type must match.
 */
class StoreExpenseRequest extends FormRequest
{
    public const MAX_KILOBYTES = 5120;

    public function authorize(): bool
    {
        $expense = $this->route('expense');

        return $expense instanceof Expense
            ? $this->user()->can('update', $expense)
            : $this->user()->can('create', Expense::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'expense_category_id' => ['required', 'integer', Rule::exists('expense_categories', 'id')],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->whereNull('deleted_at')],
            'payee_name' => ['nullable', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:500'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999', 'decimal:0,2'],
            'tax_amount' => ['nullable', 'numeric', 'min:0', 'lte:amount', 'decimal:0,2'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => [
                Rule::requiredIf(fn (): bool => PaymentMethod::tryFrom((string) $this->input('payment_method'))?->requiresReference() ?? false),
                'nullable', 'string', 'max:100',
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'extensions:pdf,jpg,jpeg,png', 'mimes:pdf,jpg,jpeg,png', 'max:'.self::MAX_KILOBYTES],
            'remove_attachment' => ['nullable', 'boolean'],
            'action' => ['nullable', 'in:save,record'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'expense_date.before_or_equal' => 'The expense date cannot be in the future.',
            'tax_amount.lte' => 'The tax cannot be more than the total amount.',
            'reference.required' => 'Enter the transaction reference (only cash payments may leave it empty).',
            'attachment.extensions' => 'Attach a PDF, JPG or PNG file.',
            'attachment.mimes' => 'Attach a PDF, JPG or PNG file.',
            'attachment.max' => 'The attachment may not be larger than 5 MB.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function expenseData(): array
    {
        return collect($this->validated())->except(['attachment', 'remove_attachment', 'action'])->all();
    }

    public function wantsToRecord(): bool
    {
        return $this->validated('action') === 'record';
    }
}
