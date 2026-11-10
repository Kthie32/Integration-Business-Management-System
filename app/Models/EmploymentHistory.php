<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A dated change to an employee's department, job title, employment type or status (FR-EMP-05).
 * Salary changes are added to this history by the payroll module.
 */
#[Fillable(['change_type', 'field', 'old_value', 'new_value', 'effective_date', 'notes', 'recorded_by'])]
class EmploymentHistory extends Model
{
    /**
     * Fields tracked in the employment history, with their display labels.
     */
    public const TRACKED_FIELDS = [
        'department_id' => 'Department',
        'job_title' => 'Job title',
        'employment_type' => 'Employment type',
        'status' => 'Status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function fieldLabel(): string
    {
        return self::TRACKED_FIELDS[$this->field] ?? ucfirst(str_replace('_', ' ', $this->field));
    }
}
