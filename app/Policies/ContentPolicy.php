<?php

namespace App\Policies;

use App\Models\User;

/** Shared least-privilege rules for website (CMS) content models. */
class ContentPolicy
{
    public function viewAny(User $user): bool { return $user->can('content.view') || $user->can('content.manage'); }
    public function view(User $user, mixed $model): bool { return $this->viewAny($user); }
    public function create(User $user): bool { return $user->can('content.manage'); }
    public function update(User $user, mixed $model): bool { return $user->can('content.manage'); }
    public function delete(User $user, mixed $model): bool { return $user->can('content.manage'); }
    public function restore(User $user, mixed $model): bool { return $user->can('content.manage'); }
    public function forceDelete(User $user, mixed $model): bool { return false; }
    public function publish(User $user, mixed $model): bool { return $user->can('content.publish'); }
}
