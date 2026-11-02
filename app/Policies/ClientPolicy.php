<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    public function viewAny(User $user): bool { return $user->can('clients.view'); }

    public function view(User $user, Client $client): bool
    {
        return $user->can('clients.view') || ($client->user_id !== null && $client->user_id === $user->getKey());
    }

    public function create(User $user): bool { return $user->can('clients.manage'); }
    public function update(User $user, Client $client): bool { return $user->can('clients.manage'); }

    /** Identification numbers are shown unmasked only to client managers. */
    public function viewIdentification(User $user, Client $client): bool { return $user->can('clients.manage'); }

    /** Clients with matters are kept for retention; mark them inactive/former instead. */
    public function delete(User $user, Client $client): bool
    {
        return $user->can('clients.manage') && $client->matters()->withTrashed()->doesntExist();
    }
}
