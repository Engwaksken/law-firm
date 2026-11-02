<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Support\Access;

class RolePolicy
{
    public function viewAny(User $user): bool { return $user->can('roles.manage'); }
    public function view(User $user, Role $role): bool { return $user->can('roles.manage'); }
    public function create(User $user): bool { return $user->can('roles.manage'); }

    /** The System Owner role is fixed: it bypasses permission checks anyway. */
    public function update(User $user, Role $role): bool
    {
        return $user->can('roles.manage') && $role->name !== Access::SYSTEM_OWNER;
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->can('roles.manage') && ! $role->isSystemRole() && $role->users()->doesntExist();
    }
}
