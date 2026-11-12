<?php

namespace App\Http\Requests\Procurement;

use App\Enums\RequisitionPriority;
use App\Models\Requisition;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Creating or revising a requisition (FR-REQ-01). Lines are catalogue items or free text; one supporting
 * document (PDF, JPG or PNG up to 5 MB) may be attached.
 */
class StoreRequisitionRequest extends FormRequest
{
    public const MAX_KILOBYTES = 5120;

    public function authorize(): bool
    {
        $requisition = $this->route('requisition');

        return $requisition instanceof Requisition
            ? $this->user()->can('update', $requisition)
            : $this->user()->can('create', Requisition::class);
    }

    /**
     * Drop completely empty line rows the form may send.
     */
    protected function prepareForValidation(): void
    {
        $lines = collect((array) $this->input('lines', []))
            ->filter(fn ($line): bool => is_array($line) && collect($line)->filter(fn ($value): bool => filled($value))->isNotEmpty())
            ->values()
            ->all();

        $this->merge(['lines' => $lines]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')->whereNull('deleted_at')],
            'required_by_date' => ['nullable', 'date'],
            'priority' => ['required', Rule::enum(RequisitionPriority::class)],
            'purpose' => ['required', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.item_id' => ['nullable', 'integer', Rule::exists('items', 'id')->whereNull('deleted_at')],
            'lines.*.description' => ['nullable', 'required_without:lines.*.item_id', 'string', 'max:255'],
            'lines.*.unit' => ['nullable', 'string', 'max:20'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:99999999', 'decimal:0,3'],
            'lines.*.estimated_unit_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999999', 'decimal:0,2'],
            'attachment' => ['nullable', 'file', 'extensions:pdf,jpg,jpeg,png', 'mimes:pdf,jpg,jpeg,png', 'max:'.self::MAX_KILOBYTES],
            'remove_attachment' => ['nullable', 'boolean'],
            'action' => ['nullable', 'in:save,submit'],
        ];
    }

    /**
     * A new requisition cannot be needed by a date in the past.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $date = $this->input('required_by_date');

                if (filled($date) && strtotime((string) $date) !== false && strtotime((string) $date) < strtotime(today()->toDateString())) {
                    $validator->errors()->add('required_by_date', 'The date needed cannot be in the past.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lines.required' => 'Add at least one line.',
            'lines.*.description.required_without' => 'Describe the line or choose a catalogue item.',
            'lines.*.quantity.required' => 'Enter a quantity.',
            'lines.*.quantity.gt' => 'The quantity must be greater than zero.',
            'attachment.extensions' => 'Attach a PDF, JPG or PNG file.',
            'attachment.mimes' => 'Attach a PDF, JPG or PNG file.',
            'attachment.max' => 'The attachment may not be larger than 5 MB.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function requisitionData(): array
    {
        return collect($this->validated())->except(['attachment', 'remove_attachment', 'action'])->all();
    }

    public function wantsToSubmit(): bool
    {
        return $this->validated('action') === 'submit';
    }
}
