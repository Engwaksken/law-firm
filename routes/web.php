<?php

use App\Http\Controllers\Website\AdvocateController;
use App\Http\Controllers\Website\CareerController;
use App\Http\Controllers\Website\CmsPageController;
use App\Http\Controllers\Website\EventController;
use App\Http\Controllers\Website\InsightController;
use App\Http\Controllers\Website\NewsletterController;
use App\Http\Controllers\Website\PageController;
use App\Http\Controllers\Website\PracticeAreaController;
use App\Http\Controllers\Website\RobotsController;
use App\Http\Controllers\Website\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::get('/book-a-consultation', [PageController::class, 'consultation'])->name('consultation');

Route::get('/practice-areas', [PracticeAreaController::class, 'index'])->name('practice-areas.index');
Route::get('/practice-areas/{practiceArea:slug}', [PracticeAreaController::class, 'show'])->name('practice-areas.show');

Route::get('/advocates', [AdvocateController::class, 'index'])->name('advocates.index');
Route::get('/advocates/{advocate:slug}', [AdvocateController::class, 'show'])->name('advocates.show');

Route::get('/insights', [InsightController::class, 'index'])->name('insights.index');
Route::get('/insights/category/{category:slug}', [InsightController::class, 'category'])->name('insights.category');
Route::get('/insights/{article:slug}', [InsightController::class, 'show'])->name('insights.show');

Route::get('/events', [EventController::class, 'index'])->name('events.index');
Route::get('/events/{event:slug}', [EventController::class, 'show'])->name('events.show');

Route::get('/careers', [CareerController::class, 'index'])->name('careers.index');
Route::get('/careers/{career:slug}', [CareerController::class, 'show'])->name('careers.show');

Route::get('/newsletter/confirm/{subscriber}', [NewsletterController::class, 'confirm'])
    ->middleware('signed')->name('newsletter.confirm');
Route::get('/newsletter/unsubscribe/{subscriber}', [NewsletterController::class, 'unsubscribe'])
    ->middleware('signed')->name('newsletter.unsubscribe');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/robots.txt', RobotsController::class)->name('robots');

// Confidential document downloads: signed + expiring link AND an authorised session (re-checked on use).
Route::get('/documents/{version}/download', \App\Http\Controllers\DocumentDownloadController::class)
    ->middleware(['auth', 'signed'])->name('documents.download');

// Personal calendar feed (entries the signed-in user can see; no client names).
Route::get('/calendar/export.ics', \App\Http\Controllers\CalendarExportController::class)
    ->middleware('auth')->name('calendar.export');

// Draft preview: staff only (signed in + allowed to view content). Never cached or indexed.
Route::get('/preview/pages/{page}', [CmsPageController::class, 'preview'])
    ->middleware(['auth'])->name('pages.preview');

// CMS pages. Must stay LAST so built-in routes always win.
Route::get('/{slug}', [CmsPageController::class, 'show'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*(?:/[a-z0-9]+(?:-[a-z0-9]+)*)*')
    ->name('pages.show');
