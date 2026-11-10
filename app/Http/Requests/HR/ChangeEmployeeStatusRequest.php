<?php

namespace App\Http\Requests\HR;

use App\Enums\EmployeeStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeEmployeeStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('changeStatus', $this->route('employee'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(EmployeeStatus::class)],
            'effective_date' => ['required', 'date', 'after_or_equal:'.$this->route('employee')->hire_date->toDateString()],
            'reason' => ['nullable', 'string', 'max:255', Rule::requiredIf(fn (): bool => $this->input('status') === EmployeeStatus::Terminated->value)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'effective_date.after_or_equal' => 'The effective date cannot be before the hire date.',
            'reason.required' => 'Give a reason for the termination.',
        ];
    }
}
