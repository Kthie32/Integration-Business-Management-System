<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\PayrollRun;
use App\Services\Payroll\PayrollReportExport;
use App\Services\Payroll\PayrollReportService;
use App\Services\System\AuditLogger;
use App\Services\System\DocumentBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

/**
 * Payroll register, statutory deductions schedule and payment list as PDF, Excel or a print view (FR-PRL-08).
 */
class PayrollExportController extends Controller
{
    public function __construct(private PayrollReportService $reports, private DocumentBranding $branding, private AuditLogger $auditLogger) {}

    public function __invoke(PayrollRun $payrollRun, string $report, string $format): Response|View
    {
        Gate::authorize('export', $payrollRun);
        abort_unless(array_key_exists($report, PayrollReportService::REPORTS) && in_array($format, ['pdf', 'xlsx', 'print'], true), 404);

        $data = $this->reports->build($payrollRun, $report);
        $fileName = trim((string) preg_replace('/[^A-Za-z0-9-]+/', '-', $payrollRun->displayNumber().'-'.$report), '-');

        if ($format === 'print') {
            return view('pdf.payroll-report', ['run' => $payrollRun, 'report' => $data, 'organization' => $this->branding->details(), 'forPdf' => false]);
        }

        $this->auditLogger->log('payroll_exported', $payrollRun, "Exported {$data['title']} ({$format})");

        if ($format === 'xlsx') {
            return Excel::download(new PayrollReportExport($data), $fileName.'.xlsx');
        }

        return Pdf::loadView('pdf.payroll-report', ['run' => $payrollRun, 'report' => $data, 'organization' => $this->branding->details(), 'forPdf' => true])
            ->setPaper('a4', 'landscape')
            ->download($fileName.'.pdf');
    }
}
