<?php

namespace App\Models;

use App\Enums\SystemRole;
use App\Models\Concerns\Auditable;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * A named bundle of permissions. System roles are seeded on installation and cannot be deleted.
 */
class Role extends SpatieRole
{
    use Auditable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function isSuperAdministrator(): bool
    {
        return $this->name === SystemRole::SuperAdministrator->value;
    }
}
