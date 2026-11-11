<?php

namespace App\Http\Requests\Sales;

use App\Enums\PaymentMethod;
use App\Models\Invoice;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A payment against one invoice. The balance check and reference uniqueness are enforced again by
 * PaymentService with the invoice locked.
 */
class RecordPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('invoice');

        return $invoice instanceof Invoice && $this->user()->can('recordPayment', $invoice);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999', 'decimal:0,2'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => [
                Rule::requiredIf(fn (): bool => PaymentMethod::tryFrom((string) $this->input('method'))?->requiresReference() ?? false),
                'nullable', 'string', 'max:100',
            ],
            'payer_name' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reference.required' => 'Enter the transaction reference (only cash payments may leave it empty).',
            'payment_date.before_or_equal' => 'The payment date cannot be in the future.',
        ];
    }

    /**
     * @return array{payment_date: string, amount: string, method: string, reference: string|null, payer_name: string|null, notes: string|null}
     */
    public function paymentData(): array
    {
        $data = $this->validated();

        return [
            'payment_date' => (string) $data['payment_date'],
            'amount' => (string) $data['amount'],
            'method' => (string) $data['method'],
            'reference' => $data['reference'] ?? null,
            'payer_name' => $data['payer_name'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];
    }
}
