<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\System\AuditLogger;
use App\Services\System\DocumentBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Printable and PDF versions of an invoice, with the organization's branding.
 */
class InvoiceDocumentController extends Controller
{
    public function __construct(private DocumentBranding $branding, private AuditLogger $auditLogger) {}

    public function pdf(Invoice $invoice): Response
    {
        Gate::authorize('view', $invoice);

        $this->auditLogger->log('pdf_downloaded', $invoice, 'Downloaded invoice PDF');

        return Pdf::loadView('pdf.invoice', $this->documentData($invoice, forPdf: true))
            ->setPaper('a4')
            ->download(trim((string) preg_replace('/[^A-Za-z0-9-]+/', '-', $invoice->displayNumber()), '-').'.pdf');
    }

    public function print(Invoice $invoice): View
    {
        Gate::authorize('view', $invoice);

        return view('pdf.invoice', $this->documentData($invoice, forPdf: false));
    }

    /**
     * @return array<string, mixed>
     */
    private function documentData(Invoice $invoice, bool $forPdf): array
    {
        return [
            'invoice' => $invoice->load('customer', 'items', 'quotation'),
            'organization' => $this->branding->details(),
            'forPdf' => $forPdf,
        ];
    }
}
