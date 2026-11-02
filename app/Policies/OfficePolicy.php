<?php

namespace App\Policies;

use App\Models\Office;
use App\Models\User;

class OfficePolicy
{
    public function viewAny(User $user): bool { return $user->can('organisation.manage') || $user->can('users.view'); }
    public function view(User $user, Office $office): bool { return $this->viewAny($user); }
    public function create(User $user): bool { return $user->can('organisation.manage'); }
    public function update(User $user, Office $office): bool { return $user->can('organisation.manage'); }
    public function delete(User $user, Office $office): bool { return $user->can('organisation.manage'); }
    public function restore(User $user, Office $office): bool { return $user->can('organisation.manage'); }
}
