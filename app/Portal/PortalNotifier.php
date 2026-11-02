<?php

namespace App\Portal;

use App\Models\Client;
use App\Models\Matter;
use App\Models\User;
use App\Notifications\Portal\PortalUpdate;
use App\Notifications\Practice\ClientActivity;
use App\Support\PortalAccess;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/** Routes portal-related notifications to the right people. */
class PortalNotifier
{
    /** Tell a client's portal users that something is waiting (respecting their email preferences). */
    public static function client(int $clientId, string $topic, string $title, string $line, string $url): void
    {
        $users = PortalAccess::usersForClient($clientId);
        if ($users->isNotEmpty()) {
            Notification::send($users, new PortalUpdate($topic, $title, $line, $url));
        }
    }

    /** Tell the matter lead and team, or (no matter) client managers, about client activity. */
    public static function staff(?Matter $matter, Client $client, string $title, array $details, string $url): void
    {
        Notification::send(static::staffFor($matter), new ClientActivity($title, $details, $url));
    }

    /** @return Collection<int, User> */
    public static function staffFor(?Matter $matter): Collection
    {
        if ($matter) {
            $ids = $matter->assignments()->pluck('user_id')->push($matter->lead_user_id)->filter()->unique();

            return User::query()->whereIn('id', $ids)->where('is_active', true)->get();
        }

        return User::query()->where('is_active', true)->permission('clients.manage')->get();
    }
}
