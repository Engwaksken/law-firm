<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class TaskPolicy extends MatterChildPolicy
{
    /** Any staff member can create personal or matter tasks (matter tasks also need createFor). */
    public function create(User $user): bool
    {
        return $user->canAccessPanel(filament()->getPanel('admin'));
    }

    public function update(User $user, Model $record): bool
    {
        /** @var Task $record */
        return $record->assigned_to === $user->id || $record->created_by === $user->id
            || ($record->matter !== null && parent::update($user, $record));
    }

    public function comment(User $user, Task $task): bool
    {
        return $task->canBeSeenBy($user);
    }

    public function delete(User $user, Model $record): bool
    {
        return $record->created_by === $user->id || ($record->matter !== null && $user->can('matters.assign') && parent::delete($user, $record));
    }
}
