<?php

namespace App\Services\Approval;

use App\Enums\ApprovableDocument;
use App\Enums\ApprovalDecision;
use App\Enums\ApprovalOutcome;
use App\Enums\ApprovalStatus;
use App\Enums\ApproverType;
use App\Events\DocumentApproved;
use App\Exceptions\BusinessRuleException;
use App\Models\Approval;
use App\Models\ApprovalWorkflow;
use App\Models\Contracts\Approvable;
use App\Models\User;
use App\Notifications\SystemMessage;
use App\Services\System\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * The generic approval engine (FR-APR-01, docs/03 §3.5).
 *
 * On submission the applicable steps of the matching workflow are copied into `approvals` as a new round.
 * Steps are decided one at a time, in order. Segregation of duties (FR-REQ-05) is enforced here for every
 * caller, Super Administrators included: the requester never decides, and nobody decides more than one step
 * of the same round. A step nobody can act on (for example the requester heads the department, docs/07 E2)
 * is skipped with a reason; if every step ends up skipped, a final "any authorised approver" step is added
 * so a document is never approved without a human decision.
 *
 * Callers run these methods inside their own transaction with the document row locked.
 */
class ApprovalService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * Start a new approval round and return the step now waiting.
     *
     * @param  Approvable&Model  $document
     */
    public function start(Approvable $document): Approval
    {
        $round = (int) $this->query($document)->max('round') + 1;
        $amount = $document->approvalAmount();
        $workflow = $this->workflowFor($document->approvableDocument(), $amount);
        $sequence = 0;

        foreach ($workflow?->steps ?? [] as $step) {
            if ($step->min_amount !== null && bccomp($amount, (string) $step->min_amount, 2) < 0) {
                continue;
            }

            $this->createStep($document, $round, ++$sequence, $step->approver_type, $step->displayName(), $step->role_id, $step->user_id, $workflow->id);
        }

        if ($sequence === 0) {
            $this->createFallbackStep($document, $round, 1, null);
        }

        $pending = $this->settle($document, $round);

        if ($pending === null) {
            throw new BusinessRuleException('The approval route could not be started.');
        }

        $document->unsetRelation('approvals');

        return $pending;
    }

    /**
     * Record a decision on the current step.
     *
     * @param  Approvable&Model  $document
     */
    public function decide(Approvable $document, User $actor, ApprovalDecision $decision, ?string $comment = null): ApprovalOutcome
    {
        $comment = filled($comment) ? trim((string) $comment) : null;

        if ($decision->requiresComment() && $comment === null) {
            throw new BusinessRuleException('Give a reason when rejecting or returning for revision.');
        }

        $round = (int) $this->query($document)->max('round');
        $steps = $this->query($document)->where('round', $round)->orderBy('sequence')->lockForUpdate()->get();
        $pending = $steps->first(fn (Approval $step): bool => $step->isPending());

        if ($pending === null) {
            throw new BusinessRuleException("{$document->approvalTitle()} is not waiting for an approval decision.");
        }

        if ($actor->id === $document->approvalRequesterId()) {
            throw new BusinessRuleException('You cannot approve, reject or return a document you requested.');
        }

        if (in_array($actor->id, $document->approvalExcludedUserIds(), true)) {
            throw new BusinessRuleException('You prepared this document, so a different person must decide it.');
        }

        if ($steps->contains(fn (Approval $step): bool => $step->decided_by === $actor->id)) {
            throw new BusinessRuleException('You have already acted on another step of this request; a different person must decide this step.');
        }

        if (! $this->isEligibleFor($actor, $document, $pending)) {
            throw new BusinessRuleException("You are not an approver for the current step ({$pending->name}).");
        }

        $pending->forceFill([
            'status' => $decision->resultingStatus(),
            'decided_by' => $actor->id,
            'comment' => $comment,
            'decided_at' => now(),
        ])->save();

        $this->auditLogger->log(
            'approval_'.$decision->resultingStatus()->value,
            $document,
            "{$document->approvalTitle()}: step \"{$pending->name}\" {$decision->resultingStatus()->label()}".($comment !== null ? " — {$comment}" : ''),
        );

        $document->unsetRelation('approvals');

        if ($decision !== ApprovalDecision::Approve) {
            $this->closeOpenSteps($document, $round, 'Not reached: the request was '.($decision === ApprovalDecision::Reject ? 'rejected.' : 'returned for revision.'));

            return $decision === ApprovalDecision::Reject ? ApprovalOutcome::Rejected : ApprovalOutcome::Returned;
        }

        if ($this->settle($document, $round) !== null) {
            return ApprovalOutcome::Advanced;
        }

        DocumentApproved::dispatch($document);

        return ApprovalOutcome::Approved;
    }

    /**
     * Close the open steps when the requester withdraws the document.
     *
     * @param  Approvable&Model  $document
     */
    public function withdraw(Approvable $document, string $reason): void
    {
        $round = (int) $this->query($document)->max('round');

        $this->closeOpenSteps($document, $round, "Not reached: {$reason}");
        $document->unsetRelation('approvals');
    }

    /**
     * Whether the user may decide the current step now.
     *
     * @param  Approvable&Model  $document
     */
    public function canAct(User $user, Approvable $document): bool
    {
        if (! $user->is_active || in_array($user->id, $document->approvalExcludedUserIds(), true)) {
            return false;
        }

        $round = (int) $this->query($document)->max('round');

        if ($round === 0) {
            return false;
        }

        $steps = $this->query($document)->where('round', $round)->orderBy('sequence')->get();
        $pending = $steps->first(fn (Approval $step): bool => $step->isPending());

        if ($pending === null || $steps->contains(fn (Approval $step): bool => $step->decided_by === $user->id)) {
            return false;
        }

        return $this->isEligibleFor($user, $document, $pending);
    }

    /**
     * Whether the user took any decision on the document, in any round.
     *
     * @param  Approvable&Model  $document
     */
    public function hasActedOn(User $user, Approvable $document): bool
    {
        return $this->query($document)->where('decided_by', $user->id)->exists();
    }

    /**
     * Current steps across all documents that the user may decide now: their "My approvals" inbox (FR-APR-02).
     *
     * @return Collection<int, Approval>
     */
    public function pendingFor(User $user): Collection
    {
        /** @var EloquentCollection<int, Approval> $pending */
        $pending = Approval::query()
            ->where('status', ApprovalStatus::Pending->value)
            ->with('approvable', 'role', 'user')
            ->orderBy('created_at')
            ->get();

        return $pending
            ->groupBy(fn (Approval $step): string => $step->approvable_type.'#'.$step->approvable_id.'#'.$step->round)
            ->map(fn (Collection $steps): Approval => $steps->sortBy('sequence')->first())
            ->filter(fn (Approval $step): bool => $step->approvable instanceof Approvable && $this->canAct($user, $step->approvable))
            ->values();
    }

    /**
     * Active users who could decide the given step, excluding the requester and anyone who already acted on
     * this round. Super Administrators are not counted here, so they never keep a step from being skipped.
     *
     * @param  Approvable&Model  $document
     * @return EloquentCollection<int, User>
     */
    public function eligibleApprovers(Approvable $document, Approval $step): EloquentCollection
    {
        $excluded = $this->query($document)
            ->where('round', $step->round)
            ->whereNotNull('decided_by')
            ->pluck('decided_by')
            ->merge($document->approvalExcludedUserIds())
            ->filter()
            ->all();

        $permission = $document->approvableDocument()->approvePermission();
        $users = User::query()->active()->whereNotIn('id', $excluded);

        return match ($step->approver_type) {
            ApproverType::Role => $step->role === null
                ? new EloquentCollection
                : $users->role($step->role)->get()->filter(fn (User $user): bool => $user->can($permission))->values(),
            ApproverType::DepartmentHead => $document->approvalDepartment()?->head_employee_id === null
                ? new EloquentCollection
                : $users->where('employee_id', $document->approvalDepartment()->head_employee_id)->get(),
            ApproverType::User => $step->user_id === null ? new EloquentCollection : $users->whereKey($step->user_id)->get(),
            ApproverType::AnyApprover => $users->permission($permission)->get(),
        };
    }

    /**
     * Tell the people who can decide the current step that it is waiting for them.
     *
     * @param  Approvable&Model  $document
     */
    public function notifyCurrentApprovers(Approvable $document): void
    {
        $round = (int) $this->query($document)->max('round');
        $pending = $this->query($document)->where('round', $round)->where('status', ApprovalStatus::Pending->value)->orderBy('sequence')->first();

        if ($pending === null) {
            return;
        }

        Notification::send($this->eligibleApprovers($document, $pending), new SystemMessage(
            "{$document->approvalTitle()} awaits your approval",
            "Step \"{$pending->name}\" is waiting for your decision.",
            $document->approvalUrl(),
            'clipboard-check',
        ));
    }

    /**
     * The active workflow for the document type with the highest minimum amount that the amount reaches.
     */
    public function workflowFor(ApprovableDocument $type, string $amount): ?ApprovalWorkflow
    {
        return ApprovalWorkflow::query()
            ->active()
            ->where('document_type', $type->value)
            ->with('steps.role', 'steps.user')
            ->get()
            ->filter(fn (ApprovalWorkflow $workflow): bool => $workflow->min_amount === null || bccomp($amount, (string) $workflow->min_amount, 2) >= 0)
            ->sort(fn (ApprovalWorkflow $a, ApprovalWorkflow $b): int => bccomp((string) ($b->min_amount ?? '-1'), (string) ($a->min_amount ?? '-1'), 2) ?: $b->id <=> $a->id)
            ->first();
    }

    /**
     * Skip steps nobody can decide until one is waiting for a person, and add the fallback step when every
     * step was skipped. Returns the waiting step, or null when the round is complete.
     *
     * @param  Approvable&Model  $document
     */
    private function settle(Approvable $document, int $round): ?Approval
    {
        while (true) {
            $steps = $this->query($document)->where('round', $round)->orderBy('sequence')->lockForUpdate()->get();
            $pending = $steps->first(fn (Approval $step): bool => $step->isPending());

            if ($pending === null) {
                if ($steps->contains(fn (Approval $step): bool => $step->status === ApprovalStatus::Approved)) {
                    return null;
                }

                return $this->createFallbackStep($document, $round, (int) $steps->max('sequence') + 1, $steps->first()?->approval_workflow_id);
            }

            if ($pending->approver_type === ApproverType::AnyApprover || $this->eligibleApprovers($document, $pending)->isNotEmpty()) {
                return $pending;
            }

            $pending->forceFill([
                'status' => ApprovalStatus::Skipped,
                'comment' => $this->skipReason($document, $pending),
                'decided_at' => now(),
            ])->save();
        }
    }

    /**
     * @param  Approvable&Model  $document
     */
    private function skipReason(Approvable $document, Approval $step): string
    {
        $requesterId = $document->approvalRequesterId();

        return match ($step->approver_type) {
            ApproverType::DepartmentHead => match (true) {
                $document->approvalDepartment()?->head_employee_id === null => 'Skipped: the department has no head.',
                User::query()->where('employee_id', $document->approvalDepartment()->head_employee_id)->whereKey($requesterId)->exists() => 'Skipped: the requester is the department head.',
                default => 'Skipped: the department head has no active user account or already acted on this request.',
            },
            ApproverType::User => in_array($step->user_id, $document->approvalExcludedUserIds(), true)
                ? 'Skipped: the named approver is the requester.'
                : 'Skipped: the named approver is inactive or already acted on this request.',
            ApproverType::Role => 'Skipped: nobody else with the '.($step->role?->name ?? 'deleted').' role can approve this.',
            ApproverType::AnyApprover => 'Skipped.',
        };
    }

    /**
     * @param  Approvable&Model  $document
     */
    private function isEligibleFor(User $user, Approvable $document, Approval $step): bool
    {
        $permission = $document->approvableDocument()->approvePermission();

        return match ($step->approver_type) {
            ApproverType::Role => $step->role !== null
                && ($user->isSuperAdministrator() || ($user->hasRole($step->role) && $user->can($permission))),
            ApproverType::DepartmentHead => $user->isHeadOf($document->approvalDepartment()),
            ApproverType::User => $step->user_id === $user->id,
            ApproverType::AnyApprover => $user->can($permission),
        };
    }

    /**
     * @param  Approvable&Model  $document
     */
    private function closeOpenSteps(Approvable $document, int $round, string $comment): void
    {
        $this->query($document)
            ->where('round', $round)
            ->where('status', ApprovalStatus::Pending->value)
            ->update(['status' => ApprovalStatus::NotReached->value, 'comment' => $comment, 'updated_at' => now()]);
    }

    /**
     * @param  Approvable&Model  $document
     */
    private function createFallbackStep(Approvable $document, int $round, int $sequence, ?int $workflowId): Approval
    {
        return $this->createStep($document, $round, $sequence, ApproverType::AnyApprover, 'Authorised approver', null, null, $workflowId);
    }

    /**
     * @param  Approvable&Model  $document
     */
    private function createStep(Approvable $document, int $round, int $sequence, ApproverType $type, string $name, ?int $roleId, ?int $userId, ?int $workflowId): Approval
    {
        $approval = new Approval;
        $approval->forceFill([
            'approvable_type' => $document->getMorphClass(),
            'approvable_id' => $document->getKey(),
            'approval_workflow_id' => $workflowId,
            'round' => $round,
            'sequence' => $sequence,
            'name' => $name,
            'approver_type' => $type,
            'role_id' => $roleId,
            'user_id' => $userId,
            'status' => ApprovalStatus::Pending,
        ])->save();

        return $approval;
    }

    /**
     * @param  Approvable&Model  $document
     * @return Builder<Approval>
     */
    private function query(Approvable $document): Builder
    {
        return Approval::query()
            ->where('approvable_type', $document->getMorphClass())
            ->where('approvable_id', $document->getKey());
    }
}
