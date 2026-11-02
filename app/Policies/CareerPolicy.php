<?php

namespace App\Policies;

use App\Models\Career;
use App\Models\User;

class CareerPolicy
{
    public function viewAny(User $user): bool { return $user->can('careers.manage'); }
    public function view(User $user, Career $career): bool { return $user->can('careers.manage'); }
    public function create(User $user): bool { return $user->can('careers.manage'); }
    public function update(User $user, Career $career): bool { return $user->can('careers.manage'); }
    public function delete(User $user, Career $career): bool { return $user->can('careers.manage'); }
    public function restore(User $user, Career $career): bool { return $user->can('careers.manage'); }
    public function forceDelete(User $user, Career $career): bool { return false; }
}
