<?php

namespace App\Http\Controllers\Procurement;

use App\Enums\ApprovalDecision;
use App\Http\Controllers\Controller;
use App\Models\Requisition;
use App\Services\Requisition\RequisitionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Submitting, deciding (approve / reject / return) and cancelling requisitions.
 */
class RequisitionWorkflowController extends Controller
{
    public function __construct(private RequisitionService $requisitions) {}

    public function submit(Request $request, Requisition $requisition): RedirectResponse
    {
        Gate::authorize('submit', $requisition);

        $requisition = $this->requisitions->submit($requisition, $request->user());

        return redirect()->route('requisitions.show', $requisition)->with('success', "Requisition {$requisition->number} submitted for approval.");
    }

    public function approve(Request $request, Requisition $requisition): RedirectResponse
    {
        return $this->decide($request, $requisition, ApprovalDecision::Approve);
    }

    public function reject(Request $request, Requisition $requisition): RedirectResponse
    {
        return $this->decide($request, $requisition, ApprovalDecision::Reject);
    }

    public function return(Request $request, Requisition $requisition): RedirectResponse
    {
        return $this->decide($request, $requisition, ApprovalDecision::Return);
    }

    public function cancel(Request $request, Requisition $requisition): RedirectResponse
    {
        Gate::authorize('cancel', $requisition);

        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $requisition = $this->requisitions->cancel($requisition, $request->user(), $data['reason']);

        return redirect()->route('requisitions.show', $requisition)->with('success', "Requisition {$requisition->number} cancelled.");
    }

    private function decide(Request $request, Requisition $requisition, ApprovalDecision $decision): RedirectResponse
    {
        Gate::authorize('decide', $requisition);

        $data = $request->validate([
            'comment' => [$decision->requiresComment() ? 'required' : 'nullable', 'string', $decision->requiresComment() ? 'min:5' : 'min:0', 'max:1000'],
        ]);

        $requisition = $this->requisitions->decide($requisition, $request->user(), $decision, $data['comment'] ?? null);

        $message = match ($decision) {
            ApprovalDecision::Approve => $requisition->status->isAwaitingApproval()
                ? "Your approval of {$requisition->number} was recorded; it has moved to the next step."
                : "Requisition {$requisition->number} is fully approved.",
            ApprovalDecision::Reject => "Requisition {$requisition->number} rejected.",
            ApprovalDecision::Return => "Requisition {$requisition->number} returned to the requester for revision.",
        };

        return redirect()->route('requisitions.show', $requisition)->with('success', $message);
    }
}
