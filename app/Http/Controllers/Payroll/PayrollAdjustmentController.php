<?php

namespace App\Http\Controllers\Payroll;

use App\Enums\PayComponentKind;
use App\Http\Controllers\Controller;
use App\Models\PayrollAdjustment;
use App\Models\PayrollRun;
use App\Services\Payroll\PayrollService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * One-off allowances and deductions for a run that is still open (docs/07 D6).
 */
class PayrollAdjustmentController extends Controller
{
    public function __construct(private PayrollService $payroll) {}

    public function store(Request $request, PayrollRun $payrollRun): RedirectResponse
    {
        Gate::authorize('prepare', $payrollRun);

        $data = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at')],
            'kind' => ['required', Rule::enum(PayComponentKind::class)],
            'description' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
            'is_taxable' => ['nullable', 'boolean'],
            'is_nssf_applicable' => ['nullable', 'boolean'],
        ]);

        $this->payroll->addAdjustment($payrollRun, $data, $request->user());

        return redirect()->to(route('payroll-runs.show', $payrollRun).'#adjustments')->with('success', 'Adjustment added.');
    }

    public function destroy(Request $request, PayrollRun $payrollRun, PayrollAdjustment $adjustment): RedirectResponse
    {
        Gate::authorize('prepare', $payrollRun);

        $this->payroll->removeAdjustment($payrollRun, $adjustment, $request->user());

        return redirect()->to(route('payroll-runs.show', $payrollRun).'#adjustments')->with('success', 'Adjustment removed.');
    }
}
