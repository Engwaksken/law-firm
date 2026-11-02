<?php

namespace App\Policies;

use App\Models\Matter;
use App\Models\User;
use App\Support\MatterAccess;

/**
 * Record-level matter access. Matter implements HasRecordLevelAccess, so even
 * the System Owner is subject to these rules (ethical walls).
 */
class MatterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('matters.view-assigned') || $user->can('matters.view-all');
    }

    public function view(User $user, Matter $matter): bool
    {
        return MatterAccess::canView($user, $matter);
    }

    public function create(User $user): bool
    {
        return $user->can('matters.create');
    }

    /** Edit details and change status: lawyers on the matter, or matter managers who can see it. */
    public function update(User $user, Matter $matter): bool
    {
        return MatterAccess::canView($user, $matter)
            && (MatterAccess::isLawyerOn($user, $matter) || $user->can('matters.assign'));
    }

    public function changeStatus(User $user, Matter $matter): bool
    {
        return $this->update($user, $matter);
    }

    /** Manage the team: matter managers, or the lead advocate. */
    public function assign(User $user, Matter $matter): bool
    {
        return MatterAccess::canView($user, $matter)
            && ($user->can('matters.assign') || $matter->lead_user_id === $user->id);
    }

    /** Contribute (notes, calendar entries, tasks, documents) on this matter. */
    public function contribute(User $user, Matter $matter): bool
    {
        return MatterAccess::canView($user, $matter)
            && $matter->status !== 'archived'
            && (MatterAccess::isAssigned($user, $matter) || $user->can('matters.assign'));
    }

    /** Matters are never deleted (retention); they are closed and archived. */
    public function delete(User $user, Matter $matter): bool { return false; }
    public function forceDelete(User $user, Matter $matter): bool { return false; }
    public function restore(User $user, Matter $matter): bool { return false; }
}
