<?php

namespace App\Http\Controllers\Payroll;

use App\Enums\PayComponentKind;
use App\Enums\PayrollRunStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\StatutoryRate;
use App\Services\Approval\ApprovalService;
use App\Services\Payroll\PayrollService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Payroll run register, creating a run for a month, the run page and deleting never-submitted runs.
 */
class PayrollRunController extends Controller
{
    public function __construct(private PayrollService $payroll, private ApprovalService $approvals) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', PayrollRun::class);

        $filters = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'status' => ['nullable', 'in:'.implode(',', array_keys(PayrollRunStatus::options()))],
        ]);

        return view('payroll.runs.index', [
            'runs' => PayrollRun::query()
                ->when($filters['year'] ?? null, fn ($query, int $year) => $query->where('period_year', $year))
                ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
                ->with('preparer', 'approver')
                ->orderByDesc('period_year')->orderByDesc('period_month')
                ->paginate(24)
                ->withQueryString(),
            'filters' => $filters,
            'statuses' => PayrollRunStatus::options(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', PayrollRun::class);

        $next = PayrollRun::query()->orderByDesc('period_year')->orderByDesc('period_month')->first()?->periodStart()->addMonth() ?? today()->startOfMonth();

        return view('payroll.runs.create', [
            'defaultYear' => $next->year,
            'defaultMonth' => $next->month,
            'months' => collect(range(1, 12))->mapWithKeys(fn (int $month): array => [$month => now()->startOfYear()->month($month)->format('F')])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', PayrollRun::class);

        $data = $request->validate([
            'period_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'period_month' => ['required', 'integer', 'min:1', 'max:12'],
            'payment_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $run = $this->payroll->create($data, $request->user());

        return redirect()->route('payroll-runs.show', $run)->with('success', "Payroll run for {$run->periodLabel()} created. Add any one-off adjustments, then calculate it.");
    }

    public function show(Request $request, PayrollRun $payrollRun): View
    {
        Gate::authorize('view', $payrollRun);

        $payrollRun->load('items', 'adjustments.employee', 'adjustments.creator', 'creator', 'preparer', 'submitter', 'approver', 'payer',
            'approvals.role', 'approvals.user', 'approvals.decider', 'statusHistories.user');

        $pending = $payrollRun->pendingApproval();
        $rates = StatutoryRate::query()->whereIn('id', $payrollRun->statutory_rate_ids ?? [])->get();

        return view('payroll.runs.show', [
            'run' => $payrollRun,
            'pendingStep' => $pending,
            'waitingFor' => $pending !== null && $payrollRun->status === PayrollRunStatus::PendingApproval
                ? $this->approvals->eligibleApprovers($payrollRun, $pending)->pluck('name')
                : collect(),
            'unverifiedRates' => ($payrollRun->status->isOpen() && $rates->isEmpty() ? collect($this->payroll->effectiveRates($payrollRun)) : $rates)
                ->reject(fn (StatutoryRate $rate): bool => $rate->isVerified())->values(),
            'employees' => $request->user()->can('prepare', $payrollRun)
                ? Employee::query()->current()->orderBy('last_name')->orderBy('first_name')->get()
                    ->mapWithKeys(fn (Employee $employee): array => [$employee->id => $employee->fullName().' ('.$employee->employee_number.')'])->all()
                : [],
            'adjustmentKinds' => PayComponentKind::options(),
        ]);
    }

    public function destroy(PayrollRun $payrollRun): RedirectResponse
    {
        Gate::authorize('delete', $payrollRun);

        $this->payroll->delete($payrollRun);

        return redirect()->route('payroll-runs.index')->with('success', "Payroll run for {$payrollRun->periodLabel()} deleted.");
    }
}
