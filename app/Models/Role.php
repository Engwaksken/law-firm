<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Support\Access;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use Auditable;

    protected $fillable = ['name', 'guard_name', 'description'];

    public function getLabelAttribute(): string
    {
        return Access::ROLES[$this->name] ?? str($this->name)->headline()->toString();
    }

    /** Built-in roles are referenced in code and must not be renamed or deleted. */
    public function isSystemRole(): bool
    {
        return array_key_exists($this->name, Access::ROLES);
    }
}
