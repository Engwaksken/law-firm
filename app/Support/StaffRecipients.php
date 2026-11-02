<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/** Who on staff should hear about a website submission. */
class StaffRecipients
{
    /** @return Collection<int, User> active, verified staff holding $permission via a role */
    public static function withPermission(string $permission): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereNotNull('email_verified_at')
            ->permission($permission)
            ->get();
    }

    /**
     * Notify staff with $permission. If none exist yet (fresh install), fall back
     * to the firm's contact email so submissions are never silently lost.
     */
    public static function notify(string $permission, object $notification): void
    {
        $staff = static::withPermission($permission);

        if ($staff->isNotEmpty()) {
            Notification::send($staff, $notification);
        } elseif ($email = Settings::get('contact_email')) {
            Notification::route('mail', $email)->notify($notification);
        }
    }
}
