<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;

/** Permissions are defined in code (App\Support\Access) and granted through roles, not edited directly. */
class PermissionPolicy
{
    public function viewAny(User $user): bool { return $user->can('roles.manage'); }
    public function view(User $user, Permission $permission): bool { return $user->can('roles.manage'); }
    public function create(User $user): bool { return false; }
    public function update(User $user, Permission $permission): bool { return false; }
    public function delete(User $user, Permission $permission): bool { return false; }
}
