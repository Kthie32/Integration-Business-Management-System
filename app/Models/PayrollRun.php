<?php

namespace App\Models;

use App\Enums\ApprovableDocument;
use App\Enums\PayrollRunStatus;
use App\Enums\PayrollRunType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasApprovals;
use App\Models\Concerns\HasStatusHistory;
use App\Models\Contracts\Approvable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A monthly payroll run (FR-PRL-04): created as a draft, calculated for every eligible employee, submitted to the
 * approval engine, then marked paid. Approved and paid runs are locked (FR-PRL-06). Nobody who created,
 * calculated or submitted a run may approve it (FR-PRL-09).
 */
class PayrollRun extends Model implements Approvable
{
    use Auditable, HasApprovals, HasStatusHistory;

    /**
     * @var list<string>
     */
    protected $guarded = ['*'];

    /**
     * Payroll figures are kept out of the audit log, which users without payroll access can read.
     *
     * @return list<string>
     */
    public function auditExclude(): array
    {
        return [
            'total_basic', 'total_allowances', 'total_gross', 'total_paye', 'total_nssf_employee', 'total_nssf_employer',
            'total_lst', 'total_other_deductions', 'total_deductions', 'total_net', 'total_employer_cost', 'skipped_employees',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PayrollRunStatus::class,
            'run_type' => PayrollRunType::class,
            'period_year' => 'integer',
            'period_month' => 'integer',
            'payment_date' => 'date',
            'employee_count' => 'integer',
            'total_basic' => 'decimal:2',
            'total_allowances' => 'decimal:2',
            'total_gross' => 'decimal:2',
            'total_paye' => 'decimal:2',
            'total_nssf_employee' => 'decimal:2',
            'total_nssf_employer' => 'decimal:2',
            'total_lst' => 'decimal:2',
            'total_other_deductions' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'total_net' => 'decimal:2',
            'total_employer_cost' => 'decimal:2',
            'statutory_rate_ids' => 'array',
            'skipped_employees' => 'array',
            'calculated_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'paid_at' => 'date',
        ];
    }

    /**
     * @return HasMany<PayrollItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class)->orderBy('employee_name');
    }

    /**
     * @return HasMany<PayrollAdjustment, $this>
     */
    public function adjustments(): HasMany
    {
        return $this->hasMany(PayrollAdjustment::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function approvableDocument(): ApprovableDocument
    {
        return ApprovableDocument::PayrollRun;
    }

    public function approvalAmount(): string
    {
        return (string) $this->total_gross;
    }

    public function approvalRequesterId(): ?int
    {
        return $this->submitted_by;
    }

    /**
     * Whoever created, calculated or submitted the run never approves it (FR-PRL-09).
     *
     * @return list<int>
     */
    public function approvalExcludedUserIds(): array
    {
        return array_values(array_unique(array_filter([$this->submitted_by, $this->prepared_by, $this->created_by])));
    }

    public function approvalDepartment(): ?Department
    {
        return null;
    }

    public function approvalTitle(): string
    {
        return 'Payroll '.$this->displayNumber().' ('.$this->periodLabel().')';
    }

    public function approvalUrl(): string
    {
        return route('payroll-runs.show', $this);
    }

    public function displayNumber(): string
    {
        return $this->number ?? 'Draft #'.$this->id;
    }

    public function periodStart(): Carbon
    {
        return Carbon::create($this->period_year, $this->period_month, 1)->startOfDay();
    }

    public function periodEnd(): Carbon
    {
        return $this->periodStart()->endOfMonth()->startOfDay();
    }

    /**
     * E.g. "November 2026".
     */
    public function periodLabel(): string
    {
        return $this->periodStart()->format('F Y');
    }

    public function isLocked(): bool
    {
        return $this->status->isLocked();
    }

    /**
     * @param  Builder<PayrollRun>  $query
     */
    public function scopeReleased(Builder $query): void
    {
        $query->whereIn('status', [PayrollRunStatus::Approved->value, PayrollRunStatus::Paid->value]);
    }
}
