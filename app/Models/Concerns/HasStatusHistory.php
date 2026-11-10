<?php

namespace App\Models\Concerns;

use App\Enums\Contracts\StatusEnum;
use App\Exceptions\InvalidStatusTransition;
use App\Models\StatusHistory;
use BackedEnum;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;

/**
 * Gives a document a status state machine and a permanent history of status changes.
 *
 * The model's status attribute must be cast to a backed enum implementing StatusEnum.
 */
trait HasStatusHistory
{
    /**
     * @return MorphMany<StatusHistory, $this>
     */
    public function statusHistories(): MorphMany
    {
        return $this->morphMany(StatusHistory::class, 'model')->latest('id');
    }

    /**
     * Move the document to a new status, refusing changes the current status does not allow.
     */
    public function transitionTo(StatusEnum&BackedEnum $status, ?string $comment = null): void
    {
        $attribute = $this->statusAttribute();

        /** @var (StatusEnum&BackedEnum)|null $current */
        $current = $this->getAttribute($attribute);

        if ($current !== null && ! $current->canTransitionTo($status)) {
            throw InvalidStatusTransition::between($current, $status);
        }

        $this->setAttribute($attribute, $status);
        $this->save();

        $this->recordStatusChange($current, $status, $comment);
    }

    /**
     * Write a history entry without changing the model, e.g. for the initial status of a new document.
     */
    public function recordStatusChange(?BackedEnum $from, BackedEnum $to, ?string $comment = null): StatusHistory
    {
        return $this->statusHistories()->create([
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'comment' => $comment,
            'user_id' => Auth::id(),
        ]);
    }

    protected function statusAttribute(): string
    {
        return 'status';
    }
}
