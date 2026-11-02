<?php

namespace App\Policies;

use App\Models\Court;
use App\Models\User;

class CourtPolicy
{
    public function viewAny(User $user): bool { return $user->can('matters.view-assigned') || $user->can('matters.view-all'); }
    public function view(User $user, Court $court): bool { return $this->viewAny($user); }
    public function create(User $user): bool { return $user->can('matters.create'); }
    public function update(User $user, Court $court): bool { return $user->can('matters.assign') || $user->can('organisation.manage'); }
    public function delete(User $user, Court $court): bool { return $this->update($user, $court) && $court->matters()->doesntExist() && $court->hearings()->doesntExist(); }
}
