<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Requisition;
use App\Services\System\AuditLogger;
use App\Services\System\DocumentBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Printable and PDF requisition with its approval trail and signature block (FR-REQ-07).
 */
class RequisitionDocumentController extends Controller
{
    public function __construct(private DocumentBranding $branding, private AuditLogger $auditLogger) {}

    public function pdf(Requisition $requisition): Response
    {
        Gate::authorize('view', $requisition);

        $this->auditLogger->log('pdf_downloaded', $requisition, 'Downloaded requisition PDF');

        return Pdf::loadView('pdf.requisition', $this->documentData($requisition, forPdf: true))
            ->setPaper('a4')
            ->download(trim((string) preg_replace('/[^A-Za-z0-9-]+/', '-', $requisition->displayNumber()), '-').'.pdf');
    }

    public function print(Requisition $requisition): View
    {
        Gate::authorize('view', $requisition);

        return view('pdf.requisition', $this->documentData($requisition, forPdf: false));
    }

    /**
     * @return array<string, mixed>
     */
    private function documentData(Requisition $requisition, bool $forPdf): array
    {
        return [
            'requisition' => $requisition->load('department', 'requester', 'items', 'approvals.role', 'approvals.user', 'approvals.decider'),
            'organization' => $this->branding->details(),
            'forPdf' => $forPdf,
        ];
    }
}
