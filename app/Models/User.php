<?php

namespace App\Models;

use App\Enums\SystemRole;
use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'phone', 'password'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'deactivated_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Login bookkeeping changes on every sign-in and is recorded by dedicated login events instead.
     *
     * @return list<string>
     */
    public function auditExclude(): array
    {
        return ['last_login_at', 'last_login_ip', 'two_factor_confirmed_at'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The employee record linked to this account (used for department-head rules and own payslips).
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * IDs of the departments whose head is this user's linked employee.
     *
     * @return list<int>
     */
    public function headedDepartmentIds(): array
    {
        if ($this->employee_id === null) {
            return [];
        }

        return Department::query()->where('head_employee_id', $this->employee_id)->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    public function isHeadOf(?Department $department): bool
    {
        return $department !== null && $this->employee_id !== null && (int) $department->head_employee_id === (int) $this->employee_id;
    }

    public function isSuperAdministrator(): bool
    {
        return $this->hasRole(SystemRole::SuperAdministrator->value);
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
