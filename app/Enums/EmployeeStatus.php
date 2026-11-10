<?php

namespace App\Enums;

use App\Enums\Contracts\StatusEnum;

/**
 * Employment status. Termination is final: a terminated employee is re-hired as a new record.
 */
enum EmployeeStatus: string implements StatusEnum
{
    case Active = 'active';
    case OnLeave = 'on_leave';
    case Suspended = 'suspended';
    case Terminated = 'terminated';

    public function canTransitionTo(StatusEnum $status): bool
    {
        return match ($this) {
            self::Active => in_array($status, [self::OnLeave, self::Suspended, self::Terminated], true),
            self::OnLeave, self::Suspended => in_array($status, [self::Active, self::Terminated], true),
            self::Terminated => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::OnLeave => 'On leave',
            self::Suspended => 'Suspended',
            self::Terminated => 'Terminated',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::OnLeave => 'info',
            self::Suspended => 'warning',
            self::Terminated => 'secondary',
        };
    }
}
