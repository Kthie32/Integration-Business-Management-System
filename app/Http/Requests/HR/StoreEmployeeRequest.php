<?php

namespace App\Http\Requests\HR;

use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Used for both registering and updating an employee. Identity and bank fields are accepted only from users
 * allowed to see them, so editing without that permission never erases them.
 */
class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $employee = $this->employee();

        return $employee !== null
            ? $this->user()->can('update', $employee)
            : $this->user()->can('create', Employee::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => filled($this->input('email')) ? Str::lower(trim((string) $this->input('email'))) : null,
            'nin' => filled($this->input('nin')) ? strtoupper(trim((string) $this->input('nin'))) : null,
            'tin' => filled($this->input('tin')) ? strtoupper(trim((string) $this->input('tin'))) : null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $employee = $this->employee();

        $rules = [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'other_names' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'date_of_birth' => ['nullable', 'date', 'before:-14 years', 'after:1900-01-01'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]+$/'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'next_of_kin_name' => ['nullable', 'string', 'max:150'],
            'next_of_kin_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]+$/'],
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'job_title' => ['required', 'string', 'max:150'],
            'employment_type' => ['required', Rule::enum(EmploymentType::class)],
            'hire_date' => ['required', 'date', 'before_or_equal:+1 year'],
            'contract_end_date' => ['nullable', 'date', 'after:hire_date', Rule::requiredIf(fn (): bool => $this->input('employment_type') === EmploymentType::Contract->value)],
            'is_resident_for_tax' => ['boolean'],
            'user_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->whereNull('employee_id')->when($employee, fn ($inner) => $inner->orWhere('employee_id', $employee->id))),
            ],
        ];

        if ($employee !== null) {
            $rules['effective_date'] = ['nullable', 'date'];
            $rules['change_notes'] = ['nullable', 'string', 'max:255'];
        }

        if ($this->maySetSensitiveDetails()) {
            $rules += [
                'nin' => ['nullable', 'string', 'size:14', 'regex:/^[A-Z0-9]+$/', Rule::unique('employees', 'nin')->ignore($employee)],
                'tin' => ['nullable', 'string', 'max:20', 'regex:/^[A-Z0-9\-]+$/'],
                'nssf_number' => ['nullable', 'string', 'max:30'],
                'bank_name' => ['nullable', 'string', 'max:100'],
                'bank_account' => ['nullable', 'string', 'max:50', 'regex:/^[0-9A-Za-z\-\s]+$/'],
                'mobile_money_number' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]+$/'],
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_of_birth.before' => 'The employee must be at least 14 years old.',
            'contract_end_date.required' => 'Contract employees need a contract end date.',
            'user_id.exists' => 'That user account is already linked to another employee.',
            'nin.size' => 'A national ID number (NIN) has 14 characters.',
        ];
    }

    /**
     * Validated data without sensitive fields the user may not set.
     *
     * @return array<string, mixed>
     */
    public function employeeData(): array
    {
        $data = $this->validated();

        return $this->maySetSensitiveDetails() ? $data : Arr::except($data, Employee::SENSITIVE_ATTRIBUTES);
    }

    private function maySetSensitiveDetails(): bool
    {
        $employee = $this->employee();

        return $employee !== null
            ? $this->user()->can('viewSensitive', $employee)
            : $this->user()->can('employees.view_salary');
    }

    private function employee(): ?Employee
    {
        $employee = $this->route('employee');

        return $employee instanceof Employee ? $employee : null;
    }
}
