<?php

use App\Http\Controllers\Approvals\ApprovalInboxController;
use App\Http\Controllers\Approvals\ApprovalWorkflowController;
use Illuminate\Support\Facades\Route;

/*
 * Approvals: the "My approvals" inbox and workflow configuration.
 */

Route::get('approvals', ApprovalInboxController::class)->name('approvals.inbox');

Route::resource('approval-workflows', ApprovalWorkflowController::class)->except(['show'])
    ->parameters(['approval-workflows' => 'approval_workflow']);
