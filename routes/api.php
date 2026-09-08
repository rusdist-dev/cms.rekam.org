<?php

use App\Http\Controllers\PublicApi\ContactController;
use App\Http\Controllers\PublicApi\EventController;
use App\Http\Controllers\PublicApi\MilestoneController;
use App\Http\Controllers\PublicApi\NewsCategoryController;
use App\Http\Controllers\PublicApi\NewsController;
use App\Http\Controllers\PublicApi\PartnerController;
use App\Http\Controllers\PublicApi\ProgramController;
use App\Http\Controllers\PublicApi\PublicationController;
use App\Http\Controllers\PublicApi\SettingsController;
use App\Http\Controllers\PublicApi\TeamController;
use App\Http\Controllers\PublicApi\UnitController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API — /api/v1/*
|--------------------------------------------------------------------------
|
| Consumed by the separate compro (company-profile) websites — no session,
| authenticated instead by the `X-Api-Key` header (ResolvePublicTenant).
| Read-only except POST contact. CORS is restricted to the compro domains
| (config/cors.php), never '*' (plan.md Fase 6).
|
*/

Route::middleware(['resolve.public.tenant', 'public.locale', \Illuminate\Routing\Middleware\SubstituteBindings::class])
    ->prefix('v1')
    ->name('public.')
    ->group(function () {
        $cacheHeaders = 'cache.headers:public;max_age='.config('cms.public_api.cache_ttl').';etag';

        Route::middleware(['throttle:public-api', $cacheHeaders])->group(function () {
            Route::middleware('feature:news')->group(function () {
                Route::get('news', [NewsController::class, 'index'])->name('news.index');
                Route::get('news/{slug}', [NewsController::class, 'show'])->name('news.show');
                Route::get('news-categories', [NewsCategoryController::class, 'index'])->name('news-categories.index');
            });

            Route::middleware('feature:news_programs')->group(function () {
                Route::get('programs', [ProgramController::class, 'index'])->name('programs.index');
            });

            Route::middleware('feature:events')->group(function () {
                Route::get('events', [EventController::class, 'index'])->name('events.index');
                Route::get('events/{slug}', [EventController::class, 'show'])->name('events.show');
            });

            Route::middleware('feature:team')->group(function () {
                Route::get('team', [TeamController::class, 'index'])->name('team.index');
            });

            Route::middleware('feature:publications')->group(function () {
                Route::get('publications', [PublicationController::class, 'index'])->name('publications.index');
            });

            Route::middleware('feature:partners')->group(function () {
                Route::get('partners', [PartnerController::class, 'index'])->name('partners.index');
            });

            Route::middleware('feature:milestones')->group(function () {
                Route::get('milestones', [MilestoneController::class, 'index'])->name('milestones.index');
            });

            Route::middleware('feature:units')->group(function () {
                Route::get('units', [UnitController::class, 'index'])->name('units.index');
            });

            // Site identity is core, not a toggleable module — no feature gate.
            Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
        });

        // Its own, much stricter limit (config('cms.public_api.contact_rate_limit'))
        // and no cache — this is a write, not a cacheable read.
        Route::middleware(['throttle:public-contact', 'feature:contacts'])->group(function () {
            Route::post('contact', [ContactController::class, 'store'])->name('contact.store');
        });
    });
