<?php

namespace App\Enums\Contracts;

/**
 * Implemented by document status enums so status changes can be validated and displayed consistently.
 */
interface StatusEnum
{
    /**
     * Whether a document in this status may move to the given status.
     */
    public function canTransitionTo(self $status): bool;

    public function label(): string;

    /**
     * A Bootstrap colour name used for the status badge (e.g. "success", "warning").
     */
    public function color(): string;
}
