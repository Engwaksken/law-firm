<?php

namespace App\Policies;

use App\Models\Matter;
use App\Models\TimeEntry;
use App\Models\User;

class TimeEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return TimeEntry::userSeesAllFinance($user) || $user->can('matters.view-assigned') || $user->can('matters.view-all');
    }

    public function view(User $user, TimeEntry $entry): bool { return $entry->canBeSeenBy($user); }

    public function create(User $user): bool { return $user->can('matters.view-assigned') || $user->can('matters.view-all'); }

    /** You record time on matters you contribute to. */
    public function createFor(User $user, Matter $matter): bool
    {
        return (new MatterPolicy)->contribute($user, $matter) && $matter->status !== 'archived';
    }

    /** Your own unbilled entries; finance may correct any unbilled entry. */
    public function update(User $user, TimeEntry $entry): bool
    {
        return $entry->status === 'unbilled' && ($entry->user_id === $user->id || $user->can('invoices.issue'));
    }

    public function delete(User $user, TimeEntry $entry): bool { return $this->update($user, $entry); }

    public function writeOff(User $user, TimeEntry $entry): bool
    {
        return $entry->status === 'unbilled' && $user->can('invoices.issue');
    }
}
