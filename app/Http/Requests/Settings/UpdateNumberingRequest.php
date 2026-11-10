<?php

namespace App\Http\Requests\Settings;

use App\Enums\DocumentType;
use App\Enums\NumberingReset;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateNumberingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('settings.finance');
    }

    /**
     * Each document type has its own form on the page, so errors go into a bag named after the type.
     */
    protected function prepareForValidation(): void
    {
        $documentType = $this->route('documentType');
        $this->errorBag = 'numbering_'.($documentType instanceof DocumentType ? $documentType->value : 'unknown');

        $this->merge(['prefix' => Str::upper(trim((string) $this->input('prefix')))]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'prefix' => ['required', 'string', 'max:10', 'alpha_num'],
            'padding' => ['required', 'integer', 'between:1,10'],
            'reset' => ['required', Rule::enum(NumberingReset::class)],
            'next_number' => ['required', 'integer', 'between:1,99999999'],
        ];
    }
}
