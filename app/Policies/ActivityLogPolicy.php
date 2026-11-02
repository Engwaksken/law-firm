<?php

namespace App\Policies;

use App\Models\ActivityLog;
use App\Models\User;

/** Audit logs are read-only for everyone, including unrestricted roles. */
class ActivityLogPolicy
{
    public function viewAny(User $user): bool { return $user->can('activity-logs.view'); }
    public function view(User $user, ActivityLog $log): bool { return $user->can('activity-logs.view'); }
    public function create(User $user): bool { return false; }
    public function update(User $user, ActivityLog $log): bool { return false; }
    public function delete(User $user, ActivityLog $log): bool { return false; }
    public function deleteAny(User $user): bool { return false; }
}
