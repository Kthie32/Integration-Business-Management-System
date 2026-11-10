<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdateFinanceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('settings.finance');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'currency_code' => Str::upper(trim((string) $this->input('currency_code'))),
            'quotation_approval_threshold' => filled($this->input('quotation_approval_threshold'))
                ? str_replace(',', '', (string) $this->input('quotation_approval_threshold'))
                : null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'currency_code' => ['required', 'string', 'size:3', 'alpha'],
            'currency_symbol' => ['required', 'string', 'max:10'],
            'currency_decimals' => ['required', 'integer', 'between:0,2'],
            'quotation_validity_days' => ['required', 'integer', 'between:1,365'],
            'invoice_payment_terms_days' => ['required', 'integer', 'between:0,365'],
            'quotation_approval_threshold' => ['nullable', 'decimal:0,2', 'min:0', 'max:9999999999999'],
        ];
    }
}
