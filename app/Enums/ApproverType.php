<?php

namespace App\Enums;

/**
 * Who may act on an approval step (FR-REQ-03). "Any authorised approver" is the engine's fallback when no
 * workflow applies or every configured step was skipped.
 */
enum ApproverType: string
{
    case Role = 'role';
    case DepartmentHead = 'department_head';
    case User = 'user';
    case AnyApprover = 'any';

    public function label(): string
    {
        return match ($this) {
            self::Role => 'Anyone with a role',
            self::DepartmentHead => 'Head of the requesting department',
            self::User => 'A named user',
            self::AnyApprover => 'Any authorised approver',
        };
    }

    /**
     * Types an administrator may choose when configuring a workflow.
     *
     * @return array<string, string>
     */
    public static function configurableOptions(): array
    {
        return collect([self::DepartmentHead, self::Role, self::User])
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }
}
