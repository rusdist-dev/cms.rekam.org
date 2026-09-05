<?php

use App\Http\Controllers\DashApi\EventApiController;
use App\Http\Controllers\DashApi\NewsApiController;
use App\Http\Controllers\DashApi\NewsCategoryApiController;
use App\Http\Controllers\DashApi\PrototypeApiController;
use App\Http\Controllers\DashApi\RoleApiController;
use App\Http\Controllers\DashApi\TaxonomyApiController;
use App\Http\Controllers\DashApi\TenantApiController;
use App\Http\Controllers\DashApi\UserApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Internal API — /dash-api/v1/*
|--------------------------------------------------------------------------
|
| Consumed by Alpine on the Blade pages. Guard is `web`: session + CSRF, not
| tokens (context.md §2.4). Every table, detail view and chart in the dashboard
| reads from here rather than from controller-supplied view data.
|
| News and events are real (Fase 3), as are taxonomy, users, roles and tenants.
| The remaining content modules still point at PrototypeApiController, which
| serves fixtures with the same response envelope, and are replaced module by
| module in Fase 4.
|
*/

Route::middleware(['web', 'auth', 'active', 'tenant'])
    ->prefix('dash-api/v1')
    ->name('dash-api.')
    ->group(function () {
        /*
         * Real endpoints (Fase 2).
         */
        Route::get('taxonomy/{group}', [TaxonomyApiController::class, 'show'])
            ->name('taxonomy.show');

        Route::middleware('permission:users.view')->group(function () {
            Route::get('users', [UserApiController::class, 'index'])->name('users.index');
            Route::get('users/{user}', [UserApiController::class, 'show'])->whereNumber('user')->name('users.show');
            Route::post('users', [UserApiController::class, 'store'])->name('users.store');
            Route::put('users/{user}', [UserApiController::class, 'update'])->whereNumber('user')->name('users.update');
            Route::delete('users/{user}', [UserApiController::class, 'destroy'])->whereNumber('user')->name('users.destroy');
        });

        Route::middleware('permission:roles.view')->group(function () {
            Route::get('permissions', [RoleApiController::class, 'permissions'])->name('permissions.index');
            Route::get('roles', [RoleApiController::class, 'index'])->name('roles.index');
            Route::get('roles/{role}', [RoleApiController::class, 'show'])->whereNumber('role')->name('roles.show');
            Route::post('roles', [RoleApiController::class, 'store'])->name('roles.store');
            Route::put('roles/{role}', [RoleApiController::class, 'update'])->whereNumber('role')->name('roles.update');
            Route::delete('roles/{role}', [RoleApiController::class, 'destroy'])->whereNumber('role')->name('roles.destroy');
        });

        Route::middleware('permission:tenants.view')->group(function () {
            Route::get('tenants', [TenantApiController::class, 'index'])->name('tenants.index');
            Route::get('tenants/{tenant}', [TenantApiController::class, 'show'])->whereNumber('tenant')->name('tenants.show');
            Route::put('tenants/{tenant}', [TenantApiController::class, 'update'])->whereNumber('tenant')->name('tenants.update');
            Route::post('tenants/{tenant}/api-key', [TenantApiController::class, 'rotateApiKey'])
                ->whereNumber('tenant')->name('tenants.api-key');
        });

        /*
         * Content modules (Fase 3).
         */
        Route::middleware(['feature:news', 'permission:news.view'])->group(function () {
            Route::get('news', [NewsApiController::class, 'index'])->name('news.index');
            Route::post('news/bulk', [NewsApiController::class, 'bulk'])->name('news.bulk');
            Route::get('news/{news}', [NewsApiController::class, 'show'])->whereNumber('news')->name('news.show');
            Route::post('news', [NewsApiController::class, 'store'])->name('news.store');
            // POST rather than PUT: a cover upload is multipart, and PHP does
            // not populate $_FILES for PUT bodies. _method is not used because
            // the front-end sends real POST.
            Route::post('news/{news}', [NewsApiController::class, 'update'])->whereNumber('news')->name('news.update');
            Route::delete('news/{news}', [NewsApiController::class, 'destroy'])->whereNumber('news')->name('news.destroy');
            Route::post('news/{news}/restore', [NewsApiController::class, 'restore'])->whereNumber('news')->name('news.restore');
            Route::delete('news/{news}/force', [NewsApiController::class, 'forceDestroy'])->whereNumber('news')->name('news.force-destroy');

            Route::get('news-categories', [NewsCategoryApiController::class, 'index'])->name('news-categories.index');
            Route::post('news-categories', [NewsCategoryApiController::class, 'store'])->name('news-categories.store');
            Route::put('news-categories/{category}', [NewsCategoryApiController::class, 'update'])->whereNumber('category')->name('news-categories.update');
            Route::delete('news-categories/{category}', [NewsCategoryApiController::class, 'destroy'])->whereNumber('category')->name('news-categories.destroy');
        });

        Route::middleware(['feature:events', 'permission:events.view'])->group(function () {
            Route::get('events', [EventApiController::class, 'index'])->name('events.index');
            Route::get('events/{event}', [EventApiController::class, 'show'])->whereNumber('event')->name('events.show');
            Route::post('events', [EventApiController::class, 'store'])->name('events.store');
            Route::post('events/{event}', [EventApiController::class, 'update'])->whereNumber('event')->name('events.update');
            Route::delete('events/{event}', [EventApiController::class, 'destroy'])->whereNumber('event')->name('events.destroy');
            Route::post('events/{event}/restore', [EventApiController::class, 'restore'])->whereNumber('event')->name('events.restore');
            Route::delete('events/{event}/force', [EventApiController::class, 'forceDestroy'])->whereNumber('event')->name('events.force-destroy');
        });

        /*
         * Prototype endpoints — replaced per module in Fase 4.
         */
        Route::get('stats/{section}', [PrototypeApiController::class, 'stats'])
            ->whereIn('section', ['summary', 'publishing_trend', 'status_breakdown', 'recent_activity', 'popular_news'])
            ->name('stats.show');

        // Module endpoints. The feature gate matches routes/web.php so a module
        // the tenant lacks is absent from the API too (context.md §5.6), and the
        // permission gate matches the page it feeds.
        $modules = [
            'team' => 'team',
            'publications' => 'publications',
            'partners' => 'partners',
            'contacts' => 'contacts',
            'milestones' => 'milestones',
            'units' => 'units',
        ];

        foreach ($modules as $resource => $feature) {
            Route::middleware(["feature:{$feature}", "permission:{$resource}.view"])
                ->group(function () use ($resource) {
                    Route::get($resource, [PrototypeApiController::class, 'index'])
                        ->defaults('resource', $resource)
                        ->name("{$resource}.index");

                    Route::get("{$resource}/{id}", [PrototypeApiController::class, 'show'])
                        ->defaults('resource', $resource)
                        ->whereNumber('id')
                        ->name("{$resource}.show");
                });
        }
    });
