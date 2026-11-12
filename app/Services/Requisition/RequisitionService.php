<?php

namespace App\Services\Requisition;

use App\Enums\ApprovalDecision;
use App\Enums\ApprovalOutcome;
use App\Enums\DocumentType;
use App\Enums\RequisitionPriority;
use App\Enums\RequisitionStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Department;
use App\Models\Item;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\User;
use App\Notifications\SystemMessage;
use App\Services\Approval\ApprovalService;
use App\Services\System\NumberingService;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Requisitions (FR-REQ-01..05): drafts are written by their requester, numbered on first submission and
 * routed through the approval engine. A returned requisition is revised and resubmitted into a new round;
 * the requester may cancel until the final decision.
 */
class RequisitionService
{
    public function __construct(private NumberingService $numbering, private ApprovalService $approvals) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createDraft(array $data, ?UploadedFile $attachment, User $actor): Requisition
    {
        $requisition = new Requisition;
        $requisition->forceFill(['status' => RequisitionStatus::Draft, 'requested_by' => $actor->id]);

        return $this->save($requisition, $data, $attachment, false, function (Requisition $requisition): void {
            $requisition->recordStatusChange(null, RequisitionStatus::Draft);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(Requisition $requisition, array $data, ?UploadedFile $attachment, bool $removeAttachment): Requisition
    {
        $this->assertEditable($requisition);

        return $this->save($requisition, $data, $attachment, $removeAttachment);
    }

    /**
     * Never-submitted drafts carry no number and are (soft) deleted.
     */
    public function deleteDraft(Requisition $requisition): void
    {
        if ($requisition->status !== RequisitionStatus::Draft || $requisition->number !== null) {
            throw new BusinessRuleException('Only a draft that was never submitted can be deleted. Cancel it instead.');
        }

        $requisition->delete();
    }

    /**
     * Number the requisition (first time only) and start an approval round.
     */
    public function submit(Requisition $requisition, User $actor): Requisition
    {
        return DB::transaction(function () use ($requisition, $actor): Requisition {
            $requisition = Requisition::query()->lockForUpdate()->with('items', 'department')->findOrFail($requisition->id);
            $this->assertEditable($requisition);

            if ($requisition->requested_by !== $actor->id) {
                throw new BusinessRuleException('Only the requester can submit this requisition.');
            }

            if ($requisition->items->isEmpty()) {
                throw new BusinessRuleException('Add at least one line before submitting.');
            }

            if ($requisition->department === null || ! $requisition->department->is_active || $requisition->department->trashed()) {
                throw new BusinessRuleException('The department is inactive. Choose an active department before submitting.');
            }

            $requisition->forceFill([
                'number' => $requisition->number ?? $this->numbering->next(DocumentType::Requisition, now()),
                'submitted_at' => now(),
            ]);
            $requisition->transitionTo(RequisitionStatus::Submitted);

            $this->approvals->start($requisition);
            $this->approvals->notifyCurrentApprovers($requisition);

            return $requisition;
        });
    }

    /**
     * Approve, reject or return the current step (FR-REQ-04).
     */
    public function decide(Requisition $requisition, User $actor, ApprovalDecision $decision, ?string $comment = null): Requisition
    {
        return DB::transaction(function () use ($requisition, $actor, $decision, $comment): Requisition {
            $requisition = Requisition::query()->lockForUpdate()->with('items', 'department')->findOrFail($requisition->id);

            if (! $requisition->status->isAwaitingApproval()) {
                throw new BusinessRuleException("Requisition {$requisition->displayNumber()} is {$requisition->status->label()} and is not waiting for approval.");
            }

            $outcome = $this->approvals->decide($requisition, $actor, $decision, $comment);

            match ($outcome) {
                ApprovalOutcome::Advanced => $this->advanced($requisition, $actor, $comment),
                ApprovalOutcome::Approved => $this->approved($requisition, $actor, $comment),
                ApprovalOutcome::Rejected => $this->closed($requisition, $actor, RequisitionStatus::Rejected, (string) $comment),
                ApprovalOutcome::Returned => $this->closed($requisition, $actor, RequisitionStatus::Returned, (string) $comment),
            };

            return $requisition;
        });
    }

    /**
     * The requester withdraws a requisition before the final decision.
     */
    public function cancel(Requisition $requisition, User $actor, string $reason): Requisition
    {
        return DB::transaction(function () use ($requisition, $actor, $reason): Requisition {
            $requisition = Requisition::query()->lockForUpdate()->findOrFail($requisition->id);

            if ($requisition->requested_by !== $actor->id) {
                throw new BusinessRuleException('Only the requester can cancel this requisition.');
            }

            if (! $requisition->status->isAwaitingApproval() && $requisition->status !== RequisitionStatus::Returned) {
                throw new BusinessRuleException("Requisition {$requisition->displayNumber()} is {$requisition->status->label()} and can no longer be cancelled.");
            }

            $this->approvals->withdraw($requisition, 'the requester cancelled the request.');
            $requisition->forceFill(['cancelled_at' => now()]);
            $requisition->transitionTo(RequisitionStatus::Cancelled, $reason);

            return $requisition;
        });
    }

    private function advanced(Requisition $requisition, User $actor, ?string $comment): void
    {
        if ($requisition->status === RequisitionStatus::Submitted) {
            $requisition->transitionTo(RequisitionStatus::InApproval, $comment);
        }

        $this->approvals->notifyCurrentApprovers($requisition);
    }

    private function approved(Requisition $requisition, User $actor, ?string $comment): void
    {
        foreach ($requisition->items as $line) {
            $line->forceFill(['approved_quantity' => $line->quantity])->save();
        }

        $requisition->forceFill(['approved_at' => now()]);
        $requisition->transitionTo(RequisitionStatus::Approved, $comment);

        $requisition->requester?->notify(new SystemMessage(
            "Requisition {$requisition->number} approved",
            "{$actor->name} gave the final approval. It will now be fulfilled.",
            route('requisitions.show', $requisition),
            'check-circle',
        ));

        Notification::send(
            User::query()->active()->permission('requisitions.fulfil')->whereKeyNot($requisition->requested_by)->get(),
            new SystemMessage(
                "Requisition {$requisition->number} is ready to fulfil",
                "{$requisition->department?->name}: ".Str::limit($requisition->purpose, 120),
                route('requisitions.show', $requisition),
                'box-arrow-up',
            ),
        );
    }

    private function closed(Requisition $requisition, User $actor, RequisitionStatus $status, string $comment): void
    {
        if ($status === RequisitionStatus::Rejected) {
            $requisition->forceFill(['rejected_at' => now()]);
        }

        $requisition->transitionTo($status, $comment);

        $requisition->requester?->notify(new SystemMessage(
            "Requisition {$requisition->number} ".($status === RequisitionStatus::Rejected ? 'rejected' : 'returned for revision'),
            "{$actor->name}: {$comment}",
            route('requisitions.show', $requisition),
            $status === RequisitionStatus::Rejected ? 'x-circle' : 'arrow-return-left',
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  (callable(Requisition): void)|null  $afterCreate
     */
    private function save(Requisition $requisition, array $data, ?UploadedFile $attachment, bool $removeAttachment, ?callable $afterCreate = null): Requisition
    {
        $department = Department::query()->findOrFail($data['department_id']);

        if (! $department->is_active && (int) $requisition->department_id !== $department->id) {
            throw new BusinessRuleException("The department \"{$department->name}\" is inactive.");
        }

        $lines = $this->prepareLines($data['lines'] ?? []);
        $oldPath = $requisition->attachment_path;
        $newPath = $attachment !== null ? $this->storeAttachment($attachment) : null;

        try {
            DB::transaction(function () use ($requisition, $data, $department, $lines, $attachment, $newPath, $removeAttachment, $afterCreate): void {
                $isNew = ! $requisition->exists;

                $requisition->forceFill([
                    'department_id' => $department->id,
                    'required_by_date' => filled($data['required_by_date'] ?? null) ? Carbon::parse($data['required_by_date'])->toDateString() : null,
                    'priority' => RequisitionPriority::tryFrom((string) ($data['priority'] ?? '')) ?? RequisitionPriority::Normal,
                    'purpose' => trim((string) $data['purpose']),
                    'estimated_total' => Money::sum(array_column($lines, 'estimated_total'))->toDecimal(),
                ]);

                if ($newPath !== null && $attachment !== null) {
                    $requisition->forceFill([
                        'attachment_path' => $newPath,
                        'attachment_name' => Str::limit(basename($attachment->getClientOriginalName()), 250, ''),
                        'attachment_mime' => (string) $attachment->getMimeType(),
                        'attachment_size' => (int) $attachment->getSize(),
                    ]);
                } elseif ($removeAttachment) {
                    $requisition->forceFill(['attachment_path' => null, 'attachment_name' => null, 'attachment_mime' => null, 'attachment_size' => null]);
                }

                $requisition->save();

                $requisition->items()->delete();

                foreach ($lines as $line) {
                    $item = new RequisitionItem;
                    $item->forceFill([...$line, 'requisition_id' => $requisition->id])->save();
                }

                if ($isNew && $afterCreate !== null) {
                    $afterCreate($requisition);
                }
            });
        } catch (Throwable $exception) {
            if ($newPath !== null) {
                Storage::disk(Requisition::DISK)->delete($newPath);
            }

            throw $exception;
        }

        if ($oldPath !== null && $oldPath !== $requisition->attachment_path) {
            Storage::disk(Requisition::DISK)->delete($oldPath);
        }

        return $requisition->load('items');
    }

    /**
     * Catalogue lines take their name and unit from the item, and its standard cost when no estimate is given.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return list<array{item_id: int|null, description: string, unit: string|null, quantity: string, estimated_unit_cost: string, estimated_total: string}>
     */
    private function prepareLines(array $lines): array
    {
        $itemIds = array_filter(array_map(fn (array $line): ?int => filled($line['item_id'] ?? null) ? (int) $line['item_id'] : null, $lines));
        $items = Item::query()->whereIn('id', $itemIds)->get()->keyBy('id');
        $prepared = [];

        foreach ($lines as $line) {
            $item = filled($line['item_id'] ?? null) ? $items->get((int) $line['item_id']) : null;

            if (filled($line['item_id'] ?? null) && ($item === null || ! $item->is_active)) {
                throw new BusinessRuleException('One of the chosen items is no longer available.');
            }

            $description = trim((string) ($line['description'] ?? '')) ?: $item?->name;

            if (blank($description)) {
                throw new BusinessRuleException('Describe each line that is not a catalogue item.');
            }

            $quantity = bcadd((string) $line['quantity'], '0', 3);

            if (bccomp($quantity, '0', 3) <= 0) {
                throw new BusinessRuleException("The quantity for \"{$description}\" must be greater than zero.");
            }

            $unitCost = Money::of(filled($line['estimated_unit_cost'] ?? null) ? (string) $line['estimated_unit_cost'] : (string) ($item?->cost_price ?? '0'));

            if ($unitCost->isNegative()) {
                throw new BusinessRuleException('Estimated costs cannot be negative.');
            }

            $prepared[] = [
                'item_id' => $item?->id,
                'description' => Str::limit($description, 250, ''),
                'unit' => $item?->unit ?? (filled($line['unit'] ?? null) ? Str::limit(trim((string) $line['unit']), 20, '') : null),
                'quantity' => $quantity,
                'estimated_unit_cost' => $unitCost->toDecimal(),
                'estimated_total' => $unitCost->multipliedBy($quantity)->toDecimal(),
            ];
        }

        if ($prepared === []) {
            throw new BusinessRuleException('Add at least one line.');
        }

        return $prepared;
    }

    private function storeAttachment(UploadedFile $file): string
    {
        return $file->storeAs(
            'requisitions/'.now()->format('Y/m'),
            Str::uuid()->toString().'.'.strtolower($file->guessExtension() ?? $file->getClientOriginalExtension()),
            Requisition::DISK,
        );
    }

    private function assertEditable(Requisition $requisition): void
    {
        if (! $requisition->isEditable()) {
            throw new BusinessRuleException("Requisition {$requisition->displayNumber()} is {$requisition->status->label()} and can no longer be changed.");
        }
    }
}
