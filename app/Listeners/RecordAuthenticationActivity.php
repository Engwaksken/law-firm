<?php

namespace App\Listeners;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;

/** Audit sign-ins, sign-outs, failed attempts and password resets. Never logs credentials. */
class RecordAuthenticationActivity
{
    public function handle(Login|Logout|Failed|PasswordReset $event): void
    {
        $user = $event->user;
        $userId = $user?->getAuthIdentifier();

        match (true) {
            $event instanceof Login => $this->login($event),
            $event instanceof Logout => ActivityLog::record('auth.logout', $user, 'Signed out', userId: $userId),
            $event instanceof PasswordReset => ActivityLog::record('auth.password-reset', $user, 'Password reset', userId: $userId),
            $event instanceof Failed => ActivityLog::record(
                'auth.failed',
                $user,
                'Failed sign-in attempt',
                // Record which account was targeted, never the password.
                ['email' => $event->credentials['email'] ?? null],
                $userId,
            ),
        };
    }

    private function login(Login $event): void
    {
        if ($event->user instanceof User) {
            // Quietly: last-login bookkeeping shouldn't create an "updated" audit entry.
            $event->user->forceFill(['last_login_at' => now(), 'last_login_ip' => request()->ip()])->saveQuietly();
        }

        ActivityLog::record('auth.login', $event->user, 'Signed in', userId: $event->user->getAuthIdentifier());
    }
}
