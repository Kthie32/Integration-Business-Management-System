<?php

namespace App\Http\Controllers\Procurement;

use App\Enums\RequisitionPriority;
use App\Enums\RequisitionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\StoreRequisitionRequest;
use App\Models\Department;
use App\Models\Item;
use App\Models\Requisition;
use App\Services\Approval\ApprovalService;
use App\Services\Requisition\RequisitionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Requisition register, drafting and revision, and attachment download.
 */
class RequisitionController extends Controller
{
    public function __construct(private RequisitionService $requisitions, private ApprovalService $approvals) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Requisition::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:'.implode(',', array_keys(RequisitionStatus::options())).',pending'],
            'department' => ['nullable', 'integer'],
            'mine' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();

        $requisitions = Requisition::query()
            ->visibleTo($user)
            ->search($filters['search'] ?? null)
            ->when($filters['status'] ?? null, fn ($query, string $status) => $status === 'pending'
                ? $query->pendingApproval()
                : $query->where('status', $status))
            ->when($filters['department'] ?? null, fn ($query, int $id) => $query->where('department_id', $id))
            ->when($filters['mine'] ?? false, fn ($query) => $query->where('requested_by', $user->id))
            ->with('department', 'requester')
            ->withCount('items')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('requisitions.index', [
            'requisitions' => $requisitions,
            'filters' => $filters,
            'departments' => Department::query()->orderBy('name')->pluck('name', 'id'),
            'statuses' => RequisitionStatus::options(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Requisition::class);

        $requisition = new Requisition;
        $requisition->forceFill([
            'department_id' => $request->user()->employee?->department_id,
            'priority' => RequisitionPriority::Normal,
        ]);

        return view('requisitions.create', ['requisition' => $requisition, ...$this->formOptions(null)]);
    }

    public function store(StoreRequisitionRequest $request): RedirectResponse
    {
        $requisition = $this->requisitions->createDraft($request->requisitionData(), $request->file('attachment'), $request->user());

        return $this->afterSave($request, $requisition, 'Draft requisition saved.');
    }

    public function show(Request $request, Requisition $requisition): View
    {
        Gate::authorize('view', $requisition);

        $requisition->load(
            'department.head', 'requester', 'items.item', 'fulfilments.requisitionItem', 'fulfilments.warehouse',
            'fulfilments.expense', 'fulfilments.fulfiller', 'approvals.role', 'approvals.user', 'approvals.decider',
            'statusHistories.user',
        );

        $pending = $requisition->pendingApproval();

        return view('requisitions.show', [
            'requisition' => $requisition,
            'pendingStep' => $pending,
            'waitingFor' => $pending !== null && $requisition->status->isAwaitingApproval()
                ? $this->approvals->eligibleApprovers($requisition, $pending)->pluck('name')
                : collect(),
        ]);
    }

    public function edit(Requisition $requisition): View
    {
        Gate::authorize('update', $requisition);

        return view('requisitions.edit', ['requisition' => $requisition->load('items'), ...$this->formOptions($requisition)]);
    }

    public function update(StoreRequisitionRequest $request, Requisition $requisition): RedirectResponse
    {
        $requisition = $this->requisitions->updateDraft($requisition, $request->requisitionData(), $request->file('attachment'), $request->boolean('remove_attachment'));

        return $this->afterSave($request, $requisition, 'Requisition updated.');
    }

    public function destroy(Requisition $requisition): RedirectResponse
    {
        Gate::authorize('delete', $requisition);

        $this->requisitions->deleteDraft($requisition);

        return redirect()->route('requisitions.index')->with('success', 'Draft requisition deleted.');
    }

    public function attachment(Requisition $requisition): StreamedResponse
    {
        Gate::authorize('view', $requisition);

        $disk = Storage::disk(Requisition::DISK);
        abort_unless($requisition->hasAttachment() && $disk->exists($requisition->attachment_path), 404);

        return $disk->download($requisition->attachment_path, $requisition->attachment_name, [
            'Content-Type' => $requisition->attachment_mime,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * "Save and submit" saves first, so a failed submission still keeps the user's input.
     */
    private function afterSave(StoreRequisitionRequest $request, Requisition $requisition, string $message): RedirectResponse
    {
        if ($request->wantsToSubmit()) {
            $requisition = $this->requisitions->submit($requisition, $request->user());
            $message = "Requisition {$requisition->number} submitted for approval.";
        }

        return redirect()->route('requisitions.show', $requisition)->with('success', $message);
    }

    /**
     * Active choices, plus the requisition's current inactive department so editing keeps it.
     *
     * @return array<string, mixed>
     */
    private function formOptions(?Requisition $requisition): array
    {
        return [
            'departments' => Department::query()
                ->where(fn ($query) => $query->where('is_active', true)->when($requisition?->department_id, fn ($query, int $id) => $query->orWhere('id', $id)))
                ->orderBy('name')->pluck('name', 'id'),
            'items' => Item::query()->active()->orderBy('name')->get(['id', 'name', 'code', 'unit', 'cost_price', 'type']),
            'priorities' => RequisitionPriority::options(),
        ];
    }
}
