<?php

namespace App\Http\Controllers\Approvals;

use App\Enums\ApprovableDocument;
use App\Enums\ApproverType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Approvals\StoreApprovalWorkflowRequest;
use App\Models\ApprovalWorkflow;
use App\Models\Role;
use App\Models\User;
use App\Services\Approval\ApprovalWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Configuring approval workflows (FR-REQ-03), with `approvals.configure`.
 */
class ApprovalWorkflowController extends Controller
{
    public function __construct(private ApprovalWorkflowService $workflows) {}

    public function index(): View
    {
        Gate::authorize('viewAny', ApprovalWorkflow::class);

        return view('approvals.workflows.index', [
            'workflows' => ApprovalWorkflow::query()->with('steps.role', 'steps.user')->orderBy('document_type')->orderBy('min_amount')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', ApprovalWorkflow::class);

        return view('approvals.workflows.create', [
            'workflow' => new ApprovalWorkflow(['document_type' => ApprovableDocument::Requisition, 'is_active' => true]),
            ...$this->formOptions(),
        ]);
    }

    public function store(StoreApprovalWorkflowRequest $request): RedirectResponse
    {
        $workflow = $this->workflows->save(new ApprovalWorkflow, $request->validated());

        return redirect()->route('approval-workflows.index')->with('success', "Workflow \"{$workflow->name}\" saved.");
    }

    public function edit(ApprovalWorkflow $approvalWorkflow): View
    {
        Gate::authorize('update', $approvalWorkflow);

        return view('approvals.workflows.edit', ['workflow' => $approvalWorkflow->load('steps'), ...$this->formOptions()]);
    }

    public function update(StoreApprovalWorkflowRequest $request, ApprovalWorkflow $approvalWorkflow): RedirectResponse
    {
        $workflow = $this->workflows->save($approvalWorkflow, $request->validated());

        return redirect()->route('approval-workflows.index')->with('success', "Workflow \"{$workflow->name}\" updated. Requests already in approval keep their original steps.");
    }

    public function destroy(ApprovalWorkflow $approvalWorkflow): RedirectResponse
    {
        Gate::authorize('delete', $approvalWorkflow);

        $this->workflows->delete($approvalWorkflow);

        return redirect()->route('approval-workflows.index')->with('success', "Workflow \"{$approvalWorkflow->name}\" deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'documentTypes' => ApprovableDocument::options(),
            'approverTypes' => ApproverType::configurableOptions(),
            'roles' => Role::query()->orderBy('name')->pluck('name', 'id'),
            'users' => User::query()->active()->orderBy('name')->pluck('name', 'id'),
        ];
    }
}
