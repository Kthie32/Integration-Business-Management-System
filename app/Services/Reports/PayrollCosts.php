<?php

namespace App\Services\Reports;

use App\Enums\PayrollRunStatus;
use App\Models\Department;
use App\Models\PayrollRun;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Payroll as an expense category on reports (FR-EXP-03, docs/07 Phase 12 decision): the cost of each approved or
 * paid run is its gross pay plus the employer's NSSF contribution, counted on the last day of the pay period.
 * A department filter uses the department recorded on each payslip when the run was calculated.
 */
class PayrollCosts
{
    /**
     * @return list<array{run: PayrollRun, date: string, employees: int, gross: string, employer_nssf: string, amount: string}>
     */
    public function between(CarbonImmutable $from, CarbonImmutable $to, ?int $departmentId = null): array
    {
        $runs = PayrollRun::query()
            ->released()
            ->whereRaw('(period_year * 100 + period_month) between ? and ?', [(int) $from->format('Ym'), (int) $to->format('Ym')])
            ->orderBy('period_year')
            ->orderBy('period_month')
            ->get()
            ->filter(fn (PayrollRun $run): bool => $run->periodEnd()->betweenIncluded($from->startOfDay(), $to->endOfDay()))
            ->keyBy('id');

        if ($runs->isEmpty()) {
            return [];
        }

        $departmentName = $departmentId !== null ? Department::query()->whereKey($departmentId)->value('name') : null;

        $sums = DB::table('payroll_items')
            ->whereIn('payroll_run_id', $runs->keys())
            ->when($departmentId !== null, fn ($query) => $query->where('department_name', $departmentName))
            ->selectRaw('payroll_run_id, COUNT(*) as employees, SUM(gross_pay) as gross, SUM(nssf_employer) as employer_nssf')
            ->groupBy('payroll_run_id')
            ->get()
            ->keyBy('payroll_run_id');

        $costs = [];

        foreach ($runs as $run) {
            $sum = $sums->get($run->id);

            if ($sum === null) {
                continue;
            }

            $gross = Money::of((string) $sum->gross)->toDecimal();
            $employer = Money::of((string) $sum->employer_nssf)->toDecimal();

            $costs[] = [
                'run' => $run,
                'date' => $run->periodEnd()->toDateString(),
                'employees' => (int) $sum->employees,
                'gross' => $gross,
                'employer_nssf' => $employer,
                'amount' => Money::of($gross)->plus($employer)->toDecimal(),
            ];
        }

        return $costs;
    }

    /**
     * @return list<string>
     */
    public static function releasedStatuses(): array
    {
        return [PayrollRunStatus::Approved->value, PayrollRunStatus::Paid->value];
    }
}
