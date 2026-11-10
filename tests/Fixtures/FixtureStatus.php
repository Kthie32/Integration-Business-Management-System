<?php

namespace Tests\Fixtures;

use App\Enums\Contracts\StatusEnum;

/**
 * A minimal document status used to test the shared status-history behaviour.
 */
enum FixtureStatus: string implements StatusEnum
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Void = 'void';

    public function canTransitionTo(StatusEnum $status): bool
    {
        return match ($this) {
            self::Draft => $status === self::Issued,
            self::Issued => $status === self::Void,
            self::Void => false,
        };
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Issued => 'primary',
            self::Void => 'danger',
        };
    }
}
