<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AppointmentPolicy extends MatterChildPolicy
{
    public function create(User $user): bool
    {
        return $user->canAccessPanel(filament()->getPanel('admin'));
    }

    public function update(User $user, Model $record): bool
    {
        /** @var Appointment $record */
        return $record->organizer_id === $user->id || ($record->matter !== null && parent::update($user, $record));
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->update($user, $record);
    }
}
