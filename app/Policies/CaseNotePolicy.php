<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CaseNotePolicy extends MatterChildPolicy
{
    protected ?string $writePermission = 'case-notes.edit';

    /** Notes are edited only by their author (others add their own note). */
    public function update(User $user, Model $record): bool
    {
        return $record->user_id === $user->id && parent::update($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        return $record->user_id === $user->id && parent::delete($user, $record);
    }
}
