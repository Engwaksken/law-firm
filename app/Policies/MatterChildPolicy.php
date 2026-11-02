<?php

namespace App\Policies;

use App\Models\Matter;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Base policy for records inside a matter. Reading follows matter visibility;
 * writing requires the right to contribute to the matter.
 */
abstract class MatterChildPolicy
{
    /** Permission needed (in addition to matter access) to write this kind of record. */
    protected ?string $writePermission = null;

    public function viewAny(User $user): bool
    {
        return $user->can('matters.view-assigned') || $user->can('matters.view-all');
    }

    public function view(User $user, Model $record): bool
    {
        return $record->canBeSeenBy($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user) && ($this->writePermission === null || $user->can($this->writePermission));
    }

    public function update(User $user, Model $record): bool
    {
        return $this->canWriteOn($user, $record->matter);
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->canWriteOn($user, $record->matter);
    }

    public function restore(User $user, Model $record): bool
    {
        return $this->canWriteOn($user, $record->matter);
    }

    public function forceDelete(User $user, Model $record): bool
    {
        return false;
    }

    /** Use when creating a record for a specific matter. */
    public function createFor(User $user, Matter $matter): bool
    {
        return $this->canWriteOn($user, $matter);
    }

    protected function canWriteOn(User $user, ?Matter $matter): bool
    {
        return $matter !== null
            && ($this->writePermission === null || $user->can($this->writePermission))
            && (new MatterPolicy)->contribute($user, $matter);
    }
}
