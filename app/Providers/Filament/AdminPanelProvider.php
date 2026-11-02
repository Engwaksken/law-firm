<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\FirmOverviewStats;
use App\Filament\Widgets\RecentActivity;
use App\Http\Middleware\EnsurePrivilegedUsersHaveMfa;
use App\Support\Settings;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->passwordReset()
            ->emailVerification()
            ->emailChangeVerification()
            ->profile(isSimple: false)
            // "Required" must be static: Filament reads it when registering routes, before any
            // user is signed in. The middleware then limits enforcement to privileged roles.
            ->multiFactorAuthentication(
                [AppAuthentication::make()->recoverable()],
                isRequired: fn (): bool => (bool) config('firm.security.enforce_mfa'),
            )
            ->multiFactorAuthenticationRequiredMiddlewareName(EnsurePrivilegedUsersHaveMfa::class)
            ->databaseNotifications()
            ->brandName(fn (): string => Settings::get('firm_name'))
            ->brandLogo(fn (): ?string => ($path = Settings::get('logo_path')) ? Storage::disk('public')->url($path) : null)
            ->darkModeBrandLogo(fn (): ?string => ($path = Settings::get('logo_dark_path') ?? Settings::get('logo_path')) ? Storage::disk('public')->url($path) : null)
            ->brandLogoHeight('2.5rem')
            ->favicon(fn (): ?string => ($path = Settings::get('favicon_path')) ? Storage::disk('public')->url($path) : null)
            ->colors(fn (): array => [
                'primary' => Color::hex(Settings::get('color_primary')),
                'danger' => Color::Rose,
                'warning' => Color::Amber,
                'success' => Color::Emerald,
                'info' => Color::Sky,
                'gray' => Color::Slate,
            ])
            ->font(fn (): string => Settings::get('font_body'))
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups([
                NavigationGroup::make('Legal Practice'),
                NavigationGroup::make('Website'),
                NavigationGroup::make('Organisation'),
                NavigationGroup::make('Access Control'),
                NavigationGroup::make('System'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                AccountWidget::class,
                FirmOverviewStats::class,
                RecentActivity::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
