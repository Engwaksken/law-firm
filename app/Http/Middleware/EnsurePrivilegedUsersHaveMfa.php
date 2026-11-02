<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Auth\MultiFactor\Http\Middleware\EnsureMultiFactorAuthenticationIsEnabled;
use Filament\Facades\Filament;
use Illuminate\Http\Request;

/**
 * Filament's "MFA required" middleware, applied only to roles listed in
 * App\Support\Access::MFA_REQUIRED_ROLES. Other staff may still opt in
 * from their profile page.
 */
class EnsurePrivilegedUsersHaveMfa extends EnsureMultiFactorAuthenticationIsEnabled
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (! config('firm.security.enforce_mfa') || ! Filament::auth()->user()?->requiresMultiFactorAuthentication()) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }
}
