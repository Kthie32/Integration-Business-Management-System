<?php

namespace App\Exceptions;

use App\Enums\Contracts\StatusEnum;

/**
 * Thrown when a document is asked to move to a status its current status does not allow.
 */
class InvalidStatusTransition extends BusinessRuleException
{
    public static function between(?StatusEnum $from, StatusEnum $to): self
    {
        return new self(sprintf(
            'A document cannot change from "%s" to "%s".',
            $from?->label() ?? 'none',
            $to->label(),
        ));
    }
}
