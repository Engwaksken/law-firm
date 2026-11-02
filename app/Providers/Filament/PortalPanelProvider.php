<?php

namespace App\Providers\Filament;

use App\Filament\Portal\Pages\Dashboard;
use App\Support\Settings;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Client portal (/portal). Separate from the staff panel: only users with the
 * client role and an active client link get in (User::canAccessPanel), and
 * everything shown is filtered through App\Support\PortalAccess.
 */
class PortalPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('portal')
            ->path('portal')
            ->login()
            ->passwordReset()
            ->emailVerification()
            ->profile()
            // Optional for clients: they may enrol from their profile.
            ->multiFactorAuthentication([AppAuthentication::make()->recoverable()])
            ->databaseNotifications()
            ->brandName(fn (): string => Settings::get('firm_name').' · Client portal')
            ->brandLogo(fn (): ?string => ($path = Settings::get('logo_path')) ? Storage::disk('public')->url($path) : null)
            ->brandLogoHeight('2.5rem')
            ->favicon(fn (): ?string => ($path = Settings::get('favicon_path')) ? Storage::disk('public')->url($path) : null)
            ->colors(fn (): array => [
                'primary' => Color::hex(preg_match('/^#[0-9A-Fa-f]{6}$/', (string) Settings::get('color_primary')) ? Settings::get('color_primary') : '#14283F'),
                'gray' => Color::Slate,
            ])
            ->font(fn (): string => Settings::get('font_body'))
            ->topNavigation()
            ->discoverResources(in: app_path('Filament/Portal/Resources'), for: 'App\Filament\Portal\Resources')
            ->discoverPages(in: app_path('Filament/Portal/Pages'), for: 'App\Filament\Portal\Pages')
            ->pages([Dashboard::class])
            ->widgets([])
            // File downloads for clients: inside the portal's auth middleware (canAccessPanel),
            // so staff accounts can't use them; each controller re-checks PortalAccess.
            ->authenticatedRoutes(function (): void {
                \Illuminate\Support\Facades\Route::prefix('files')->name('files.')->group(function (): void {
                    \Illuminate\Support\Facades\Route::get('/documents/{version}', \App\Http\Controllers\Portal\PortalDocumentController::class)
                        ->middleware('signed')->name('document');
                    \Illuminate\Support\Facades\Route::get('/invoices/{invoice}.pdf', [\App\Http\Controllers\Portal\PortalFinanceController::class, 'invoice'])->name('invoice');
                    \Illuminate\Support\Facades\Route::get('/receipts/{receipt}.pdf', [\App\Http\Controllers\Portal\PortalFinanceController::class, 'receipt'])->name('receipt');
                    \Illuminate\Support\Facades\Route::get('/statements/{client}.pdf', [\App\Http\Controllers\Portal\PortalFinanceController::class, 'statement'])->name('statement');
                });
            })
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
            ->authMiddleware([Authenticate::class]);
    }
}
