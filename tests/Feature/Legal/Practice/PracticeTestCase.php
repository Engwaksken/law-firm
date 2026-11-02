<?php

namespace Tests\Feature\Legal\Practice;

use App\Http\Controllers\CalendarExportController;
use App\Http\Controllers\DocumentDownloadController;
use App\Models\Matter;
use App\Models\User;
use App\Support\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

abstract class PracticeTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $lead;
    protected User $outsider;
    protected Matter $matter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
        $this->withoutMfaEnforcement();
        Storage::fake(config('firm.documents.disk'));

        // The parent registers these in routes/web.php; define them here if not yet present.
        if (! Route::has('documents.download') || ! Route::has('calendar.export')) {
            $router = app('router');
            if (! Route::has('documents.download')) {
                Route::get('/documents/{version}/download', DocumentDownloadController::class)->middleware(['web', 'auth', 'signed'])->name('documents.download');
            }
            if (! Route::has('calendar.export')) {
                Route::get('/calendar/export.ics', CalendarExportController::class)->middleware(['web', 'auth'])->name('calendar.export');
            }
            // Keep the CMS catch-all (/{slug}) last, as it is in routes/web.php.
            $ordered = new \Illuminate\Routing\RouteCollection;
            $catchAll = null;
            foreach ($router->getRoutes()->getRoutes() as $route) {
                $route->getName() === 'pages.show' ? $catchAll = $route : $ordered->add($route);
            }
            if ($catchAll) {
                $ordered->add($catchAll);
            }
            $router->setRoutes($ordered);
        }

        $this->lead = $this->userWithRole(Access::ADVOCATE, ['name' => 'Lead Advocate']);
        $this->outsider = $this->userWithRole(Access::ADVOCATE, ['name' => 'Outside Advocate']);
        $this->matter = Matter::factory()->active()->ledBy($this->lead)->create();
    }
}
