<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PayrollLineKind;
use App\Enums\PayslipPaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One employee's pay in a payroll run: a snapshot of the employee details used, the totals and the stored line
 * breakdown (FR-PRL-05). It is the payslip; it gets its payslip number when the run is approved.
 */
class PayrollItem extends Model
{
    /**
     * @var list<string>
     */
    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_resident_for_tax' => 'boolean',
            'payment_method' => PaymentMethod::class,
            'payment_status' => PayslipPaymentStatus::class,
            'basic_salary' => 'decimal:2',
            'total_allowances' => 'decimal:2',
            'gross_pay' => 'decimal:2',
            'taxable_pay' => 'decimal:2',
            'nssf_pay' => 'decimal:2',
            'paye' => 'decimal:2',
            'nssf_employee' => 'decimal:2',
            'nssf_employer' => 'decimal:2',
            'lst' => 'decimal:2',
            'other_deductions' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_pay' => 'decimal:2',
            'paid_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<PayrollRun, $this>
     */
    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    /**
     * @return HasMany<PayrollItemLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PayrollItemLine::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return Collection<int, PayrollItemLine>
     */
    public function earnings(): Collection
    {
        return $this->lines->filter(fn (PayrollItemLine $line): bool => $line->kind->isEarning())->values();
    }

    /**
     * @return Collection<int, PayrollItemLine>
     */
    public function deductions(): Collection
    {
        return $this->lines->filter(fn (PayrollItemLine $line): bool => $line->kind->isDeduction())->values();
    }

    /**
     * @return Collection<int, PayrollItemLine>
     */
    public function employerContributions(): Collection
    {
        return $this->lines->filter(fn (PayrollItemLine $line): bool => $line->kind === PayrollLineKind::EmployerContribution)->values();
    }

    public function displayNumber(): string
    {
        return $this->payslip_number ?? 'Payslip (not approved)';
    }

    /**
     * Where the net pay goes, e.g. "Stanbic Bank · 9030012345".
     */
    public function paymentDestination(): string
    {
        return match ($this->payment_method) {
            PaymentMethod::BankTransfer => collect([$this->bank_name, $this->bank_account])->filter()->implode(' · '),
            PaymentMethod::MobileMoney => (string) $this->mobile_money_number,
            default => 'Cash',
        };
    }

    /**
     * Payslips of approved or paid runs, the only ones employees may see.
     *
     * @param  Builder<PayrollItem>  $query
     */
    public function scopeReleased(Builder $query): void
    {
        $query->whereHas('payrollRun', fn (Builder $runs) => $runs->released());
    }
}
