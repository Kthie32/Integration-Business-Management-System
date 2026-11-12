<?php

namespace App\Http\Controllers\Payroll;

use App\Enums\ApprovalDecision;
use App\Enums\PayrollRunStatus;
use App\Http\Controllers\Controller;
use App\Models\PayrollRun;
use App\Services\Payroll\PayrollService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Calculating, submitting, deciding (approve / return for correction) and marking payroll runs paid.
 */
class PayrollRunWorkflowController extends Controller
{
    public function __construct(private PayrollService $payroll) {}

    public function calculate(Request $request, PayrollRun $payrollRun): RedirectResponse
    {
        Gate::authorize('prepare', $payrollRun);

        $run = $this->payroll->calculate($payrollRun, $request->user());
        $skipped = count($run->skipped_employees ?? []);

        return redirect()->route('payroll-runs.show', $run)->with('success', "Calculated pay for {$run->employee_count} ".str('employee')->plural($run->employee_count).'.'
            .($skipped > 0 ? " {$skipped} skipped — see the list below." : ''));
    }

    public function submit(Request $request, PayrollRun $payrollRun): RedirectResponse
    {
        Gate::authorize('submit', $payrollRun);

        $run = $this->payroll->submit($payrollRun, $request->user());

        return redirect()->route('payroll-runs.show', $run)->with('success', "Payroll {$run->number} submitted for approval.");
    }

    public function approve(Request $request, PayrollRun $payrollRun): RedirectResponse
    {
        return $this->decide($request, $payrollRun, ApprovalDecision::Approve);
    }

    public function return(Request $request, PayrollRun $payrollRun): RedirectResponse
    {
        return $this->decide($request, $payrollRun, ApprovalDecision::Return);
    }

    public function markPaid(Request $request, PayrollRun $payrollRun): RedirectResponse
    {
        Gate::authorize('markPaid', $payrollRun);

        $data = $request->validate([
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
        ]);

        $run = $this->payroll->markPaid($payrollRun, $request->user(), $data['paid_at'], $data['payment_reference'] ?? null);

        return redirect()->route('payroll-runs.show', $run)->with('success', "Payroll {$run->number} marked as paid.");
    }

    private function decide(Request $request, PayrollRun $payrollRun, ApprovalDecision $decision): RedirectResponse
    {
        Gate::authorize('decide', $payrollRun);

        $data = $request->validate([
            'comment' => [$decision->requiresComment() ? 'required' : 'nullable', 'string', $decision->requiresComment() ? 'min:5' : 'min:0', 'max:1000'],
        ]);

        $run = $this->payroll->decide($payrollRun, $request->user(), $decision, $data['comment'] ?? null);

        $message = match (true) {
            $decision === ApprovalDecision::Return => "Payroll {$run->number} returned for correction.",
            $run->status === PayrollRunStatus::Approved => "Payroll {$run->number} is approved. Payslips are numbered and the run is locked.",
            default => "Your approval of {$run->number} was recorded; it has moved to the next step.",
        };

        return redirect()->route('payroll-runs.show', $run)->with('success', $message);
    }
}
