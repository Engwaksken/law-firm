<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Access;

class UserPolicy
{
    public function viewAny(User $user): bool { return $user->can('users.view') || $user->can('users.manage'); }
    public function view(User $user, User $target): bool { return $this->viewAny($user) || $user->is($target); }
    public function create(User $user): bool { return $user->can('users.manage'); }

    public function update(User $user, User $target): bool
    {
        return $user->can('users.manage') && $this->mayTouch($user, $target);
    }

    public function delete(User $user, User $target): bool
    {
        return $user->can('users.manage') && ! $user->is($target) && $this->mayTouch($user, $target);
    }

    public function restore(User $user, User $target): bool { return $this->delete($user, $target); }
    public function forceDelete(User $user, User $target): bool { return false; }

    /** Only a System Owner may modify a System Owner account. */
    private function mayTouch(User $user, User $target): bool
    {
        return ! $target->hasRole(Access::SYSTEM_OWNER) || $user->hasRole(Access::SYSTEM_OWNER);
    }
}
