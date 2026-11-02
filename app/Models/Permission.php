<?php

namespace App\Models;

use App\Support\Access;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    public function getLabelAttribute(): string
    {
        return Access::permissionLabels()[$this->name] ?? $this->name;
    }
}
