<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(config('firm.security.password_min_length'))->mixedCase()->numbers()->symbols()->uncompromised()
            : Password::min(8));

        // System Owner bypasses authorization, except that audit logs stay immutable.
        Gate::before(function (User $user, string $ability, array $arguments) {
            $target = $arguments[0] ?? null;
            $isAuditWrite = ($target instanceof ActivityLog || $target === ActivityLog::class)
                && ! in_array($ability, ['view', 'viewAny'], true);

            return ! $isAuditWrite && $user->isUnrestricted() ? true : null;
        });

        // Audit listeners in app/Listeners are registered by Laravel's event discovery.
    }
}
