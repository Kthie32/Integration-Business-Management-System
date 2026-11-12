<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\PayrollItem;
use App\Services\System\AuditLogger;
use App\Services\System\DocumentBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Payslips (FR-PRL-07): the payslip page, PDF and print view, and "My payslips" for employees whose user account
 * is linked to their employee record.
 */
class PayslipController extends Controller
{
    public function __construct(private DocumentBranding $branding, private AuditLogger $auditLogger) {}

    public function mine(Request $request): View
    {
        abort_unless($request->user()->can('payslips.view_own'), 403);

        $employeeId = $request->user()->employee_id;

        return view('payroll.payslips.mine', [
            'linked' => $employeeId !== null,
            'payslips' => $employeeId === null ? collect() : PayrollItem::query()
                ->where('employee_id', $employeeId)
                ->released()
                ->with('payrollRun')
                ->get()
                ->sortByDesc(fn (PayrollItem $item): int => $item->payrollRun->period_year * 100 + $item->payrollRun->period_month)
                ->values(),
        ]);
    }

    public function show(PayrollItem $payslip): View
    {
        Gate::authorize('view', $payslip);

        return view('payroll.payslips.show', ['item' => $payslip->load('payrollRun', 'lines', 'employee')]);
    }

    public function pdf(PayrollItem $payslip): Response
    {
        Gate::authorize('view', $payslip);

        $this->auditLogger->log('pdf_downloaded', $payslip->payrollRun, "Downloaded payslip {$payslip->displayNumber()} ({$payslip->employee_name})");

        return Pdf::loadView('pdf.payslip', $this->documentData($payslip, forPdf: true))
            ->setPaper('a4')
            ->download(trim((string) preg_replace('/[^A-Za-z0-9-]+/', '-', $payslip->payslip_number ?? 'payslip-'.$payslip->id), '-').'.pdf');
    }

    public function print(PayrollItem $payslip): View
    {
        Gate::authorize('view', $payslip);

        return view('pdf.payslip', $this->documentData($payslip, forPdf: false));
    }

    /**
     * @return array<string, mixed>
     */
    private function documentData(PayrollItem $payslip, bool $forPdf): array
    {
        return [
            'item' => $payslip->load('payrollRun', 'lines'),
            'organization' => $this->branding->details(),
            'forPdf' => $forPdf,
        ];
    }
}
