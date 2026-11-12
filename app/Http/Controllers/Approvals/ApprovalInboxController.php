<?php

namespace App\Http\Controllers\Approvals;

use App\Http\Controllers\Controller;
use App\Services\Approval\ApprovalInbox;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "My approvals" inbox (FR-APR-02). Any signed-in user may be a named approver, so the page is open to all;
 * it only ever lists items the user can decide.
 */
class ApprovalInboxController extends Controller
{
    public function __invoke(Request $request, ApprovalInbox $inbox): View
    {
        return view('approvals.inbox', ['items' => $inbox->itemsFor($request->user())]);
    }
}
