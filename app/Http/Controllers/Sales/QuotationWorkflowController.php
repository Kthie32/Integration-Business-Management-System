<?php

namespace App\Http\Controllers\Sales;

use App\Enums\QuotationStatus;
use App\Http\Controllers\Controller;
use App\Models\Quotation;
use App\Services\Sales\QuotationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Status changes on a quotation: submit, approve/return, sent, the customer's decision, cancel and revise.
 */
class QuotationWorkflowController extends Controller
{
    public function __construct(private QuotationService $quotations) {}

    public function submit(Request $request, Quotation $quotation): RedirectResponse
    {
        Gate::authorize('submit', $quotation);

        $quotation = $this->quotations->submit($quotation, $request->user());

        $message = $quotation->status === QuotationStatus::PendingApproval
            ? "{$quotation->number} was submitted for approval."
            : "{$quotation->number} is ready to send to the customer.";

        return redirect()->route('quotations.show', $quotation)->with('success', $message);
    }

    public function approve(Request $request, Quotation $quotation): RedirectResponse
    {
        Gate::authorize('approve', $quotation);

        $data = $request->validate(['comment' => ['nullable', 'string', 'max:500']]);
        $quotation = $this->quotations->approve($quotation, $request->user(), $data['comment'] ?? null);

        return redirect()->route('quotations.show', $quotation)->with('success', "{$quotation->number} approved.");
    }

    public function returnToDraft(Request $request, Quotation $quotation): RedirectResponse
    {
        Gate::authorize('approve', $quotation);

        $data = $request->validate(['comment' => ['required', 'string', 'min:5', 'max:500']]);
        $quotation = $this->quotations->returnToDraft($quotation, $request->user(), $data['comment']);

        return redirect()->route('quotations.show', $quotation)->with('success', "{$quotation->number} was returned to its author for changes.");
    }

    public function markSent(Quotation $quotation): RedirectResponse
    {
        Gate::authorize('markSent', $quotation);

        $quotation = $this->quotations->markSent($quotation);

        return redirect()->route('quotations.show', $quotation)->with('success', "{$quotation->number} marked as sent.");
    }

    public function accept(Request $request, Quotation $quotation): RedirectResponse
    {
        Gate::authorize('recordDecision', $quotation);

        $data = $request->validate(['comment' => ['nullable', 'string', 'max:500']]);
        $quotation = $this->quotations->markAccepted($quotation, $data['comment'] ?? null);

        return redirect()->route('quotations.show', $quotation)->with('success', "{$quotation->number} marked as accepted by the customer.");
    }

    public function reject(Request $request, Quotation $quotation): RedirectResponse
    {
        Gate::authorize('recordDecision', $quotation);

        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        $quotation = $this->quotations->markRejected($quotation, $data['reason']);

        return redirect()->route('quotations.show', $quotation)->with('success', "{$quotation->number} marked as rejected by the customer.");
    }

    public function cancel(Request $request, Quotation $quotation): RedirectResponse
    {
        Gate::authorize('cancel', $quotation);

        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $quotation = $this->quotations->cancel($quotation, $data['reason']);

        return redirect()->route('quotations.show', $quotation)->with('success', "{$quotation->displayNumber()} cancelled.");
    }

    public function revise(Request $request, Quotation $quotation): RedirectResponse
    {
        Gate::authorize('revise', $quotation);

        $revision = $this->quotations->revise($quotation, $request->user());

        return redirect()->route('quotations.edit', $revision)
            ->with('success', "Revision {$revision->revision} of {$quotation->number} created. {$quotation->number} stays valid until you submit this revision.");
    }
}
