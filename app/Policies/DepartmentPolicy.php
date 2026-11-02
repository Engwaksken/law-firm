<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    public function viewAny(User $user): bool { return $user->can('organisation.manage') || $user->can('users.view'); }
    public function view(User $user, Department $department): bool { return $this->viewAny($user); }
    public function create(User $user): bool { return $user->can('organisation.manage'); }
    public function update(User $user, Department $department): bool { return $user->can('organisation.manage'); }
    public function delete(User $user, Department $department): bool { return $user->can('organisation.manage'); }
    public function restore(User $user, Department $department): bool { return $user->can('organisation.manage'); }
}
