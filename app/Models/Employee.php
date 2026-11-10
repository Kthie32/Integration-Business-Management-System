<?php

namespace App\Models;

use App\Enums\EmployeeStatus;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasStatusHistory;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A person employed by the organization. Identity numbers and bank details are sensitive: they are shown only
 * to users with employees.view_salary and are masked in the audit log.
 */
#[Fillable([
    'first_name', 'last_name', 'other_names', 'gender', 'date_of_birth', 'nin', 'tin', 'nssf_number',
    'phone', 'email', 'address', 'next_of_kin_name', 'next_of_kin_phone', 'department_id', 'job_title',
    'employment_type', 'hire_date', 'contract_end_date', 'is_resident_for_tax', 'bank_name', 'bank_account',
    'mobile_money_number',
])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use Auditable {
        auditableAttributes as unmaskedAuditableAttributes;
    }

    use HasFactory, HasStatusHistory, SoftDeletes;

    /**
     * Attributes visible only with the employees.view_salary permission.
     */
    public const SENSITIVE_ATTRIBUTES = ['nin', 'tin', 'nssf_number', 'bank_name', 'bank_account', 'mobile_money_number'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'employment_type' => EmploymentType::class,
            'status' => EmployeeStatus::class,
            'date_of_birth' => 'date',
            'hire_date' => 'date',
            'contract_end_date' => 'date',
            'termination_date' => 'date',
            'is_resident_for_tax' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class)->withTrashed();
    }

    /**
     * The user account linked to this employee, if any.
     *
     * @return HasOne<User, $this>
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    /**
     * @return HasMany<EmployeeDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class)->latest('id');
    }

    /**
     * @return HasMany<EmploymentHistory, $this>
     */
    public function employmentHistories(): HasMany
    {
        return $this->hasMany(EmploymentHistory::class)->latest('effective_date')->latest('id');
    }

    /**
     * @return HasMany<Department, $this>
     */
    public function headedDepartments(): HasMany
    {
        return $this->hasMany(Department::class, 'head_employee_id');
    }

    public function fullName(): string
    {
        return trim(implode(' ', array_filter([$this->first_name, $this->other_names, $this->last_name])));
    }

    /**
     * @param  Builder<Employee>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->where('status', '!=', EmployeeStatus::Terminated->value);
    }

    /**
     * Limit the query to employees the user may see: everyone with employees.view, otherwise only employees of
     * the departments the user's linked employee heads.
     *
     * @param  Builder<Employee>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->can('employees.view')) {
            return;
        }

        if (! $user->can('employees.view_department') || $user->employee_id === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn('department_id', Department::query()->where('head_employee_id', $user->employee_id)->select('id'));
    }

    /**
     * Sensitive values are recorded as changed without revealing them in the audit log.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function auditableAttributes(array $attributes): array
    {
        $values = $this->unmaskedAuditableAttributes($attributes);

        foreach (self::SENSITIVE_ATTRIBUTES as $attribute) {
            if (array_key_exists($attribute, $values) && $values[$attribute] !== null) {
                $values[$attribute] = '[hidden]';
            }
        }

        return $values;
    }
}
