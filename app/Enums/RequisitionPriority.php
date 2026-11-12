<?php

namespace App\Enums;

/**
 * How urgently the requester needs a requisition.
 */
enum RequisitionPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Normal => 'Normal',
            self::High => 'High',
            self::Urgent => 'Urgent',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Low => 'secondary',
            self::Normal => 'primary',
            self::High => 'warning',
            self::Urgent => 'danger',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $priority): array => [$priority->value => $priority->label()])->all();
    }
}
