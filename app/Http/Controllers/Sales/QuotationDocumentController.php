<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Quotation;
use App\Services\System\AuditLogger;
use App\Services\System\DocumentBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Printable and PDF versions of a quotation, with the organization's branding.
 */
class QuotationDocumentController extends Controller
{
    public function __construct(private DocumentBranding $branding, private AuditLogger $auditLogger) {}

    public function pdf(Quotation $quotation): Response
    {
        Gate::authorize('view', $quotation);

        $this->auditLogger->log('pdf_downloaded', $quotation, 'Downloaded quotation PDF');

        return Pdf::loadView('pdf.quotation', $this->documentData($quotation, forPdf: true))
            ->setPaper('a4')
            ->download(trim((string) preg_replace('/[^A-Za-z0-9-]+/', '-', $quotation->displayNumber()), '-').'.pdf');
    }

    public function print(Quotation $quotation): View
    {
        Gate::authorize('view', $quotation);

        return view('pdf.quotation', $this->documentData($quotation, forPdf: false));
    }

    /**
     * @return array<string, mixed>
     */
    private function documentData(Quotation $quotation, bool $forPdf): array
    {
        return [
            'quotation' => $quotation->load('customer', 'items', 'approver'),
            'organization' => $this->branding->details(),
            'forPdf' => $forPdf,
        ];
    }
}
