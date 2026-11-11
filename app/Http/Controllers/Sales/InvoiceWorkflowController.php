<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Services\Sales\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Status changes on an invoice: issue, void, corrected copy, and conversion from an accepted quotation.
 */
class InvoiceWorkflowController extends Controller
{
    public function __construct(private InvoiceService $invoices) {}

    public function issue(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('issue', $invoice);

        $invoice = $this->invoices->issue($invoice, $request->user());

        return redirect()->route('invoices.show', $invoice)->with('success', "Invoice {$invoice->number} issued.");
    }

    public function void(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('void', $invoice);

        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $invoice = $this->invoices->void($invoice, $request->user(), $data['reason']);

        return redirect()->route('invoices.show', $invoice)->with('success', "{$invoice->number} voided. Any stock it issued was returned.");
    }

    public function copy(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('copy', $invoice);

        $copy = $this->invoices->createCorrectedCopy($invoice, $request->user());

        return redirect()->route('invoices.edit', $copy)->with('success', "Corrected draft created from {$invoice->number}. Review it and issue it when ready.");
    }

    public function convertQuotation(Request $request, Quotation $quotation): RedirectResponse
    {
        Gate::authorize('convert', $quotation);

        $invoice = $this->invoices->createFromQuotation($quotation, $request->user());

        return redirect()->route('invoices.show', $invoice)->with('success', "Draft invoice created from {$quotation->number}. Check the warehouse and dates, then issue it.");
    }
}
