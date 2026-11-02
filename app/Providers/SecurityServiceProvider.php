<?php

namespace App\Providers;

use App\Security\Scanning\ClamAvScanner;
use App\Security\Scanning\NullScanner;
use App\Security\Scanning\Scanner;
use Illuminate\Support\ServiceProvider;

class SecurityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Scanner::class, fn () => match (config('firm.scanner.driver')) {
            'clamav' => new ClamAvScanner(
                (string) config('firm.scanner.clamav_socket'),
                (int) config('firm.scanner.clamav_timeout', 30),
            ),
            default => new NullScanner,
        });
    }
}
