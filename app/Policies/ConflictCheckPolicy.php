<?php

namespace App\Policies;

use App\Models\ConflictCheck;
use App\Models\User;

class ConflictCheckPolicy
{
    public function viewAny(User $user): bool { return $user->can('conflicts.run') || $user->can('conflicts.review'); }
    public function view(User $user, ConflictCheck $check): bool { return $this->viewAny($user); }
    public function create(User $user): bool { return $user->can('conflicts.run'); }

    /** Clearing a conflict is a professional judgement: reviewers only, and never your own search. */
    public function review(User $user, ConflictCheck $check): bool
    {
        return $check->status === 'pending_review' && $user->can('conflicts.review') && $check->requested_by !== $user->id;
    }

    public function update(User $user, ConflictCheck $check): bool { return false; }
    public function delete(User $user, ConflictCheck $check): bool { return false; }
}
