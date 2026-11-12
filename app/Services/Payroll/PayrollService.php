<?php

namespace App\Services\Payroll;

use App\Enums\ApprovalDecision;
use App\Enums\ApprovalOutcome;
use App\Enums\DocumentType;
use App\Enums\EmployeeStatus;
use App\Enums\PayComponentCalculation;
use App\Enums\PayComponentKind;
use App\Enums\PaymentMethod;
use App\Enums\PayrollRunStatus;
use App\Enums\PayrollRunType;
use App\Enums\PayslipPaymentStatus;
use App\Enums\StatutoryRateCode;
use App\Exceptions\BusinessRuleException;
use App\Models\Employee;
use App\Models\PayrollAdjustment;
use App\Models\PayrollItem;
use App\Models\PayrollItemLine;
use App\Models\PayrollRun;
use App\Models\SalaryStructure;
use App\Models\StatutoryRate;
use App\Models\User;
use App\Notifications\SystemMessage;
use App\Services\Approval\ApprovalService;
use App\Services\System\AuditLogger;
use App\Services\System\NumberingService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Monthly payroll runs (FR-PRL-04..09, docs/03 §3.6).
 *
 * A run is created as a draft for a month, calculated for every eligible employee in one transaction (items and
 * line breakdowns are stored so payslips are reproducible), recalculated as often as needed, then submitted to
 * the approval engine. Whoever created, calculated or submitted the run cannot approve it. Approval gives every
 * payslip its number and locks the run; Finance then marks it paid. Corrections after approval are one-off
 * adjustments in the next month's run (docs/07 D6).
 *
 * Eligible employees: hired by the end of the month, not suspended, and not terminated before the month began.
 * Employees without a salary structure in effect are listed as skipped. Pay is not pro-rated for part months.
 */
class PayrollService
{
    public function __construct(
        private PayrollCalculator $calculator,
        private ApprovalService $approvals,
        private NumberingService $numbering,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{period_year: int|string, period_month: int|string, payment_date?: string|null, notes?: string|null}  $data
     */
    public function create(array $data, User $actor): PayrollRun
    {
        $year = (int) $data['period_year'];
        $month = (int) $data['period_month'];
        $period = Carbon::create($year, $month, 1)->startOfDay();

        if ($period->greaterThan(today()->startOfMonth()->addMonth())) {
            throw new BusinessRuleException('Payroll can be prepared for the current or next month at the latest.');
        }

        return DB::transaction(function () use ($data, $year, $month, $period, $actor): PayrollRun {
            $existing = PayrollRun::query()->where('period_year', $year)->where('period_month', $month)
                ->where('run_type', PayrollRunType::Regular->value)->lockForUpdate()->first();

            if ($existing !== null) {
                throw new BusinessRuleException("A payroll run for {$period->format('F Y')} already exists ({$existing->displayNumber()}).");
            }

            $run = new PayrollRun;
            $run->forceFill([
                'period_year' => $year,
                'period_month' => $month,
                'run_type' => PayrollRunType::Regular,
                'status' => PayrollRunStatus::Draft,
                'payment_date' => filled($data['payment_date'] ?? null) ? Carbon::parse($data['payment_date'])->toDateString() : null,
                'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
                'created_by' => $actor->id,
            ])->save();

            $run->recordStatusChange(null, PayrollRunStatus::Draft);

            return $run;
        });
    }

    /**
     * Never-submitted runs are removed with their items and adjustments.
     */
    public function delete(PayrollRun $run): void
    {
        DB::transaction(function () use ($run): void {
            $run = PayrollRun::query()->lockForUpdate()->findOrFail($run->id);

            if (! $run->status->isOpen() || $run->number !== null) {
                throw new BusinessRuleException('Only a payroll run that was never submitted can be deleted.');
            }

            $run->delete();
        });
    }

    /**
     * Work out every eligible employee's pay and store the items, lines and totals.
     */
    public function calculate(PayrollRun $run, User $actor): PayrollRun
    {
        return DB::transaction(function () use ($run, $actor): PayrollRun {
            $run = PayrollRun::query()->lockForUpdate()->findOrFail($run->id);

            if (! $run->status->isOpen()) {
                throw new BusinessRuleException("Payroll {$run->displayNumber()} is {$run->status->label()} and can no longer be recalculated.");
            }

            $this->compute($run);

            $run->forceFill(['prepared_by' => $actor->id, 'calculated_at' => now()]);

            if ($run->status === PayrollRunStatus::Draft) {
                $run->transitionTo(PayrollRunStatus::Calculated);
            } else {
                $run->save();
                $this->auditLogger->log('payroll_recalculated', $run, "Payroll {$run->displayNumber()} ({$run->periodLabel()}) recalculated");
            }

            return $run;
        });
    }

    /**
     * Add a one-off allowance or deduction for one employee. A calculated run is recalculated straight away.
     *
     * @param  array{employee_id: int|string, kind: string, description: string, amount: string, is_taxable?: bool, is_nssf_applicable?: bool}  $data
     */
    public function addAdjustment(PayrollRun $run, array $data, User $actor): PayrollAdjustment
    {
        return DB::transaction(function () use ($run, $data, $actor): PayrollAdjustment {
            $run = $this->lockOpenRun($run);
            $employee = Employee::query()->findOrFail($data['employee_id']);
            $amount = Money::of((string) $data['amount']);

            if (! $amount->isPositive()) {
                throw new BusinessRuleException('The adjustment amount must be greater than zero.');
            }

            $adjustment = new PayrollAdjustment;
            $adjustment->forceFill([
                'payroll_run_id' => $run->id,
                'employee_id' => $employee->id,
                'kind' => PayComponentKind::from($data['kind']),
                'description' => trim((string) $data['description']),
                'amount' => $amount->toDecimal(),
                'is_taxable' => (bool) ($data['is_taxable'] ?? false),
                'is_nssf_applicable' => (bool) ($data['is_nssf_applicable'] ?? false),
                'created_by' => $actor->id,
            ])->save();

            $this->auditLogger->log('payroll_adjustment_added', $run, "Adjustment \"{$adjustment->description}\" added for {$employee->fullName()}");
            $this->recalculateIfCalculated($run, $actor);

            return $adjustment;
        });
    }

    public function removeAdjustment(PayrollRun $run, PayrollAdjustment $adjustment, User $actor): void
    {
        DB::transaction(function () use ($run, $adjustment, $actor): void {
            $run = $this->lockOpenRun($run);

            if ($adjustment->payroll_run_id !== $run->id) {
                throw new BusinessRuleException('That adjustment belongs to another payroll run.');
            }

            $adjustment->delete();
            $this->auditLogger->log('payroll_adjustment_removed', $run, "Adjustment \"{$adjustment->description}\" removed");
            $this->recalculateIfCalculated($run, $actor);
        });
    }

    /**
     * Number the run (first time only) and start an approval round.
     */
    public function submit(PayrollRun $run, User $actor): PayrollRun
    {
        return DB::transaction(function () use ($run, $actor): PayrollRun {
            $run = PayrollRun::query()->lockForUpdate()->findOrFail($run->id);

            if ($run->status !== PayrollRunStatus::Calculated) {
                throw new BusinessRuleException("Payroll {$run->displayNumber()} is {$run->status->label()}. Calculate it before submitting.");
            }

            if ($run->employee_count === 0) {
                throw new BusinessRuleException('The run has no employees to pay.');
            }

            $run->forceFill([
                'number' => $run->number ?? $this->numbering->next(DocumentType::PayrollRun, now()),
                'submitted_by' => $actor->id,
                'submitted_at' => now(),
            ]);
            $run->transitionTo(PayrollRunStatus::PendingApproval);

            $this->approvals->start($run);
            $this->approvals->notifyCurrentApprovers($run);

            return $run;
        });
    }

    /**
     * Approve the current step, or return the run for correction (FR-PRL-09).
     */
    public function decide(PayrollRun $run, User $actor, ApprovalDecision $decision, ?string $comment = null): PayrollRun
    {
        return DB::transaction(function () use ($run, $actor, $decision, $comment): PayrollRun {
            $run = PayrollRun::query()->lockForUpdate()->findOrFail($run->id);

            if ($run->status !== PayrollRunStatus::PendingApproval) {
                throw new BusinessRuleException("Payroll {$run->displayNumber()} is {$run->status->label()} and is not waiting for approval.");
            }

            $outcome = $this->approvals->decide($run, $actor, $decision, $comment);

            match ($outcome) {
                ApprovalOutcome::Advanced => $this->approvals->notifyCurrentApprovers($run),
                ApprovalOutcome::Approved => $this->approved($run, $actor, $comment),
                ApprovalOutcome::Rejected, ApprovalOutcome::Returned => $this->returned($run, $actor, (string) $comment),
            };

            return $run;
        });
    }

    /**
     * Record that the approved net pay was paid out.
     */
    public function markPaid(PayrollRun $run, User $actor, string $paidOn, ?string $reference): PayrollRun
    {
        return DB::transaction(function () use ($run, $actor, $paidOn, $reference): PayrollRun {
            $run = PayrollRun::query()->lockForUpdate()->findOrFail($run->id);
            $date = Carbon::parse($paidOn)->startOfDay();

            if ($run->status !== PayrollRunStatus::Approved) {
                throw new BusinessRuleException("Payroll {$run->displayNumber()} is {$run->status->label()}; only an approved run can be marked paid.");
            }

            if ($date->greaterThan(today())) {
                throw new BusinessRuleException('The payment date cannot be in the future.');
            }

            if ($date->lessThan($run->approved_at->copy()->startOfDay())) {
                throw new BusinessRuleException('The payment date cannot be before the run was approved ('.$run->approved_at->toDateString().').');
            }

            $run->items()->update(['payment_status' => PayslipPaymentStatus::Paid->value, 'paid_at' => $date->toDateString(), 'updated_at' => now()]);
            $run->forceFill([
                'paid_by' => $actor->id,
                'paid_at' => $date->toDateString(),
                'payment_reference' => filled($reference) ? trim((string) $reference) : null,
            ]);
            $run->transitionTo(PayrollRunStatus::Paid);

            $this->notifyEmployees($run);

            return $run;
        });
    }

    /**
     * Statutory rule sets in effect at the end of the run's month, keyed by code.
     *
     * @return array<string, StatutoryRate>
     */
    public function effectiveRates(PayrollRun $run): array
    {
        return StatutoryRate::query()
            ->effectiveOn($run->periodEnd()->toDateString())
            ->get()
            ->keyBy(fn (StatutoryRate $rate): string => $rate->code->value)
            ->all();
    }

    private function compute(PayrollRun $run): void
    {
        $rates = $this->effectiveRates($run);

        foreach ([StatutoryRateCode::PayeResident, StatutoryRateCode::Nssf] as $required) {
            if (! isset($rates[$required->value])) {
                throw new BusinessRuleException("No {$required->label()} rules are in effect for {$run->periodLabel()}. Add them under Payroll → Statutory rates.");
            }
        }

        $rules = [
            'paye_resident' => $rates[StatutoryRateCode::PayeResident->value]->rules,
            'paye_non_resident' => isset($rates[StatutoryRateCode::PayeNonResident->value]) ? $rates[StatutoryRateCode::PayeNonResident->value]->rules : null,
            'nssf' => $rates[StatutoryRateCode::Nssf->value]->rules,
            'lst' => isset($rates[StatutoryRateCode::Lst->value]) ? $rates[StatutoryRateCode::Lst->value]->rules : null,
        ];

        $start = $run->periodStart();
        $end = $run->periodEnd();
        $adjustments = $run->adjustments()->get()->groupBy('employee_id');
        $employees = Employee::query()
            ->with('department')
            ->whereDate('hire_date', '<=', $end->toDateString())
            ->where(fn ($query) => $query
                ->where('status', '!=', EmployeeStatus::Terminated->value)
                ->orWhereDate('termination_date', '>=', $start->toDateString()))
            ->orderBy('last_name')->orderBy('first_name')
            ->get();

        $run->items()->delete();

        $skipped = [];
        $negative = [];
        $totals = array_fill_keys(['basic_salary', 'total_allowances', 'gross_pay', 'paye', 'nssf_employee', 'nssf_employer', 'lst', 'other_deductions', 'total_deductions', 'net_pay'], Money::zero());
        $count = 0;

        foreach ($employees as $employee) {
            if ($employee->status === EmployeeStatus::Suspended) {
                $skipped[] = $this->skip($employee, 'Suspended');

                continue;
            }

            $onDate = $employee->status === EmployeeStatus::Terminated && $employee->termination_date !== null && $employee->termination_date->lessThan($end)
                ? $employee->termination_date->toDateString()
                : $end->toDateString();
            $structure = SalaryStructure::query()->where('employee_id', $employee->id)->effectiveOn($onDate)->with('components.payComponent')->first();

            if ($structure === null) {
                $skipped[] = $this->skip($employee, 'No salary structure in effect');

                continue;
            }

            if (! $employee->is_resident_for_tax && $rules['paye_non_resident'] === null) {
                throw new BusinessRuleException("{$employee->fullName()} is a non-resident for tax, but no non-resident PAYE rules are in effect for {$run->periodLabel()}.");
            }

            $result = $this->calculator->calculate([
                'basic_salary' => (string) $structure->basic_salary,
                'is_resident' => (bool) $employee->is_resident_for_tax,
                'month' => $run->period_month,
                'components' => [...$this->structureComponents($structure), ...$this->adjustmentComponents($adjustments->get($employee->id, new Collection))],
            ], $rules);

            if (Money::of($result['net_pay'])->isNegative()) {
                $negative[] = $employee->fullName();

                continue;
            }

            $this->storeItem($run, $employee, $structure, $result);
            $count++;

            foreach ($totals as $key => $total) {
                $totals[$key] = $total->plus($result[$key]);
            }
        }

        if ($negative !== []) {
            throw new BusinessRuleException('Deductions are more than gross pay for: '.implode(', ', $negative).'. Reduce their deductions or adjustments and calculate again.');
        }

        if ($count === 0) {
            throw new BusinessRuleException("Nobody could be paid for {$run->periodLabel()}. Record salary structures for the employees first.");
        }

        $run->forceFill([
            'employee_count' => $count,
            'total_basic' => $totals['basic_salary']->toDecimal(),
            'total_allowances' => $totals['total_allowances']->toDecimal(),
            'total_gross' => $totals['gross_pay']->toDecimal(),
            'total_paye' => $totals['paye']->toDecimal(),
            'total_nssf_employee' => $totals['nssf_employee']->toDecimal(),
            'total_nssf_employer' => $totals['nssf_employer']->toDecimal(),
            'total_lst' => $totals['lst']->toDecimal(),
            'total_other_deductions' => $totals['other_deductions']->toDecimal(),
            'total_deductions' => $totals['total_deductions']->toDecimal(),
            'total_net' => $totals['net_pay']->toDecimal(),
            'total_employer_cost' => $totals['gross_pay']->plus($totals['nssf_employer'])->toDecimal(),
            'statutory_rate_ids' => collect($rates)->pluck('id')->sort()->values()->all(),
            'skipped_employees' => $skipped,
        ]);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function storeItem(PayrollRun $run, Employee $employee, SalaryStructure $structure, array $result): void
    {
        $method = match (true) {
            filled($employee->bank_account) => PaymentMethod::BankTransfer,
            filled($employee->mobile_money_number) => PaymentMethod::MobileMoney,
            default => PaymentMethod::Cash,
        };

        $item = new PayrollItem;
        $item->forceFill([
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'salary_structure_id' => $structure->id,
            'employee_number' => $employee->employee_number,
            'employee_name' => $employee->fullName(),
            'department_name' => $employee->department?->name,
            'job_title' => $employee->job_title,
            'tin' => $employee->tin,
            'nssf_number' => $employee->nssf_number,
            'is_resident_for_tax' => (bool) $employee->is_resident_for_tax,
            'payment_method' => $method,
            'bank_name' => $employee->bank_name,
            'bank_account' => $employee->bank_account,
            'mobile_money_number' => $employee->mobile_money_number,
            'basic_salary' => $result['basic_salary'],
            'total_allowances' => $result['total_allowances'],
            'gross_pay' => $result['gross_pay'],
            'taxable_pay' => $result['taxable_pay'],
            'nssf_pay' => $result['nssf_pay'],
            'paye' => $result['paye'],
            'nssf_employee' => $result['nssf_employee'],
            'nssf_employer' => $result['nssf_employer'],
            'lst' => $result['lst'],
            'other_deductions' => $result['other_deductions'],
            'total_deductions' => $result['total_deductions'],
            'net_pay' => $result['net_pay'],
            'payment_status' => PayslipPaymentStatus::Pending,
        ])->save();

        $now = now();

        PayrollItemLine::query()->insert(array_map(fn (array $line, int $index): array => [
            'payroll_item_id' => $item->id,
            'pay_component_id' => $line['pay_component_id'],
            'code' => $line['code'],
            'description' => mb_substr($line['description'], 0, 150),
            'kind' => $line['kind']->value,
            'amount' => $line['amount'],
            'sort_order' => $index,
            'created_at' => $now,
            'updated_at' => $now,
        ], $result['lines'], array_keys($result['lines'])));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function structureComponents(SalaryStructure $structure): array
    {
        return $structure->components->map(fn ($line): array => [
            'pay_component_id' => $line->pay_component_id,
            'code' => $line->payComponent->code,
            'name' => $line->payComponent->name,
            'kind' => $line->payComponent->kind,
            'calculation' => $line->payComponent->calculation,
            'value' => (string) $line->value,
            'is_taxable' => $line->payComponent->is_taxable,
            'is_nssf_applicable' => $line->payComponent->is_nssf_applicable,
        ])->values()->all();
    }

    /**
     * @param  Collection<int, PayrollAdjustment>  $adjustments
     * @return list<array<string, mixed>>
     */
    private function adjustmentComponents(Collection $adjustments): array
    {
        return $adjustments->map(fn (PayrollAdjustment $adjustment): array => [
            'pay_component_id' => null,
            'code' => 'ADJ',
            'name' => $adjustment->description,
            'kind' => $adjustment->kind,
            'calculation' => PayComponentCalculation::Fixed,
            'value' => (string) $adjustment->amount,
            'is_taxable' => $adjustment->is_taxable,
            'is_nssf_applicable' => $adjustment->is_nssf_applicable,
        ])->values()->all();
    }

    /**
     * @return array{employee_id: int, employee_number: string, name: string, reason: string}
     */
    private function skip(Employee $employee, string $reason): array
    {
        return ['employee_id' => $employee->id, 'employee_number' => $employee->employee_number, 'name' => $employee->fullName(), 'reason' => $reason];
    }

    private function lockOpenRun(PayrollRun $run): PayrollRun
    {
        $run = PayrollRun::query()->lockForUpdate()->findOrFail($run->id);

        if (! $run->status->isOpen()) {
            throw new BusinessRuleException("Payroll {$run->displayNumber()} is {$run->status->label()}; adjustments can no longer change. Add them to next month's run.");
        }

        return $run;
    }

    private function recalculateIfCalculated(PayrollRun $run, User $actor): void
    {
        if ($run->status !== PayrollRunStatus::Calculated) {
            return;
        }

        $this->compute($run);
        $run->forceFill(['prepared_by' => $actor->id, 'calculated_at' => now()])->save();
    }

    private function approved(PayrollRun $run, User $actor, ?string $comment): void
    {
        foreach ($run->items()->orderBy('employee_name')->orderBy('id')->get() as $item) {
            $item->forceFill(['payslip_number' => $this->numbering->next(DocumentType::Payslip, now())])->save();
        }

        $run->forceFill(['approved_by' => $actor->id, 'approved_at' => now()]);
        $run->transitionTo(PayrollRunStatus::Approved, $comment);

        $message = new SystemMessage(
            "Payroll {$run->number} ({$run->periodLabel()}) approved",
            "{$actor->name} gave the final approval. Net pay ".Money::of((string) $run->total_net)->format().' is ready to be paid.',
            route('payroll-runs.show', $run),
            'check-circle',
        );

        $recipients = User::query()->active()->permission('payroll.mark_paid')->get()
            ->merge(User::query()->active()->whereIn('id', $run->approvalExcludedUserIds())->get())
            ->unique('id')
            ->reject(fn (User $user): bool => $user->id === $actor->id);

        Notification::send($recipients, $message);
    }

    private function returned(PayrollRun $run, User $actor, string $comment): void
    {
        $run->transitionTo(PayrollRunStatus::Calculated, $comment);

        Notification::send(User::query()->active()->whereIn('id', $run->approvalExcludedUserIds())->get(), new SystemMessage(
            "Payroll {$run->number} ({$run->periodLabel()}) returned for correction",
            "{$actor->name}: {$comment}",
            route('payroll-runs.show', $run),
            'arrow-return-left',
        ));
    }

    /**
     * Tell employees with a linked account that their payslip is available (FR-PRL-07).
     */
    private function notifyEmployees(PayrollRun $run): void
    {
        $users = User::query()->active()
            ->whereIn('employee_id', $run->items()->select('employee_id'))
            ->get()
            ->filter(fn (User $user): bool => $user->can('payslips.view_own'));

        foreach ($users as $user) {
            $item = $run->items()->where('employee_id', $user->employee_id)->first();

            if ($item !== null) {
                $user->notify(new SystemMessage(
                    "Your payslip for {$run->periodLabel()} is available",
                    "Payslip {$item->payslip_number}.",
                    route('payslips.show', $item),
                    'file-earmark-text',
                ));
            }
        }
    }
}
