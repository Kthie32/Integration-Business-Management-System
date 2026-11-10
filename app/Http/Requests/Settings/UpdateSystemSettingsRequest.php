<?php

namespace App\Http\Requests\Settings;

use App\Services\System\SettingsService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSystemSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('settings.system');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email_notifications' => $this->boolean('email_notifications'),
            'low_stock_check' => $this->boolean('low_stock_check'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date_format' => ['required', Rule::in(array_keys(SettingsService::DATE_FORMATS))],
            'email_notifications' => ['boolean'],
            'low_stock_check' => ['boolean'],
        ];
    }
}
