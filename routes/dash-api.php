<?php

use App\Http\Controllers\DashApi\ActivityLogApiController;
use App\Http\Controllers\DashApi\ContactMessageApiController;
use App\Http\Controllers\DashApi\ContactSettingsApiController;
use App\Http\Controllers\DashApi\EventApiController;
use App\Http\Controllers\DashApi\MilestoneApiController;
use App\Http\Controllers\DashApi\NewsApiController;
use App\Http\Controllers\DashApi\NewsCategoryApiController;
use App\Http\Controllers\DashApi\PartnerApiController;
use App\Http\Controllers\DashApi\PublicationApiController;
use App\Http\Controllers\DashApi\RoleApiController;
use App\Http\Controllers\DashApi\SiteIdentityApiController;
use App\Http\Controllers\DashApi\SiteSeoApiController;
use App\Http\Controllers\DashApi\SiteSettingsApiController;
use App\Http\Controllers\DashApi\StatsApiController;
use App\Http\Controllers\DashApi\TaxonomyApiController;
use App\Http\Controllers\DashApi\TeamMemberApiController;
use App\Http\Controllers\DashApi\TenantApiController;
use App\Http\Controllers\DashApi\UnitApiController;
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

        // The taxonomy editor (Pengaturan > Taksonomi) needs the raw bilingual
        // shape and a write path — unlike `show` above, which every content
        // form's select/multi-select reads and must keep returning the
        // flattened, single-locale shape it already does.
        Route::middleware('permission:settings.view')->group(function () {
            Route::get('taxonomy/{group}/edit', [TaxonomyApiController::class, 'edit'])->name('taxonomy.edit');
        });
        Route::middleware('permission:settings.update')->group(function () {
            Route::put('taxonomy/{group}', [TaxonomyApiController::class, 'update'])->name('taxonomy.update');
        });

        // Pengaturan Situs — singleton settings per tenant, not taxonomy.
        Route::middleware('permission:settings.view')->group(function () {
            Route::get('settings/identity', [SiteIdentityApiController::class, 'show'])->name('settings.identity.show');
            Route::get('settings/seo', [SiteSeoApiController::class, 'show'])->name('settings.seo.show');
            Route::get('settings/{group}', [SiteSettingsApiController::class, 'show'])
                ->whereIn('group', ['socials', 'map'])->name('settings.show');
        });
        Route::middleware('permission:settings.update')->group(function () {
            // POST rather than PUT: logo/favicon/og_image uploads are
            // multipart, and PHP does not populate $_FILES on PUT bodies.
            Route::post('settings/identity', [SiteIdentityApiController::class, 'update'])->name('settings.identity.update');
            Route::post('settings/seo', [SiteSeoApiController::class, 'update'])->name('settings.seo.update');
            Route::put('settings/{group}', [SiteSettingsApiController::class, 'update'])
                ->whereIn('group', ['socials', 'map'])->name('settings.update');
        });

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
         * Content modules (Fase 4).
         */
        Route::middleware(['feature:team', 'permission:team.view'])->group(function () {
            Route::get('team', [TeamMemberApiController::class, 'index'])->name('team.index');
            Route::get('team/{team}', [TeamMemberApiController::class, 'show'])->whereNumber('team')->name('team.show');
            Route::post('team', [TeamMemberApiController::class, 'store'])->name('team.store');
            // POST rather than PUT for update — same $_FILES reason as news/events.
            Route::post('team/{team}', [TeamMemberApiController::class, 'update'])->whereNumber('team')->name('team.update');
            Route::delete('team/{team}', [TeamMemberApiController::class, 'destroy'])->whereNumber('team')->name('team.destroy');
            Route::patch('team/reorder', [TeamMemberApiController::class, 'reorder'])->name('team.reorder');
        });

        Route::middleware(['feature:partners', 'permission:partners.view'])->group(function () {
            Route::get('partners', [PartnerApiController::class, 'index'])->name('partners.index');
            Route::get('partners/{partner}', [PartnerApiController::class, 'show'])->whereNumber('partner')->name('partners.show');
            Route::post('partners', [PartnerApiController::class, 'store'])->name('partners.store');
            Route::post('partners/{partner}', [PartnerApiController::class, 'update'])->whereNumber('partner')->name('partners.update');
            Route::delete('partners/{partner}', [PartnerApiController::class, 'destroy'])->whereNumber('partner')->name('partners.destroy');
            Route::patch('partners/reorder', [PartnerApiController::class, 'reorder'])->name('partners.reorder');
        });

        Route::middleware(['feature:publications', 'permission:publications.view'])->group(function () {
            Route::get('publications', [PublicationApiController::class, 'index'])->name('publications.index');
            Route::get('publications/{publication}', [PublicationApiController::class, 'show'])->whereNumber('publication')->name('publications.show');
            Route::post('publications', [PublicationApiController::class, 'store'])->name('publications.store');
            Route::post('publications/{publication}', [PublicationApiController::class, 'update'])->whereNumber('publication')->name('publications.update');
            Route::delete('publications/{publication}', [PublicationApiController::class, 'destroy'])->whereNumber('publication')->name('publications.destroy');
        });

        Route::middleware(['feature:contacts', 'permission:contacts.view'])->group(function () {
            Route::get('contacts', [ContactMessageApiController::class, 'index'])->name('contacts.index');
            // Registered before {message} so the literal segment is matched first
            // (the whereNumber constraint below already makes this safe either way).
            Route::get('contacts/settings', [ContactSettingsApiController::class, 'show'])->name('contacts.settings.show');
            Route::get('contacts/{message}', [ContactMessageApiController::class, 'show'])->whereNumber('message')->name('contacts.show');
            Route::patch('contacts/{message}/archive', [ContactMessageApiController::class, 'archive'])->whereNumber('message')->name('contacts.archive');
            Route::delete('contacts/{message}', [ContactMessageApiController::class, 'destroy'])->whereNumber('message')->name('contacts.destroy');
        });

        Route::middleware(['feature:contacts', 'permission:contacts.update'])->group(function () {
            Route::put('contacts/settings', [ContactSettingsApiController::class, 'update'])->name('contacts.settings.update');
        });

        /*
         * Content modules (Fase 4b) — tenant-exclusive: the table itself only
         * exists in the one tenant's database (context.md §5.5).
         */
        Route::middleware(['feature:milestones', 'permission:milestones.view'])->group(function () {
            Route::get('milestones', [MilestoneApiController::class, 'index'])->name('milestones.index');
            Route::get('milestones/{milestone}', [MilestoneApiController::class, 'show'])->whereNumber('milestone')->name('milestones.show');
            Route::post('milestones', [MilestoneApiController::class, 'store'])->name('milestones.store');
            Route::post('milestones/{milestone}', [MilestoneApiController::class, 'update'])->whereNumber('milestone')->name('milestones.update');
            Route::delete('milestones/{milestone}', [MilestoneApiController::class, 'destroy'])->whereNumber('milestone')->name('milestones.destroy');
            Route::patch('milestones/reorder', [MilestoneApiController::class, 'reorder'])->name('milestones.reorder');
        });

        Route::middleware(['feature:units', 'permission:units.view'])->group(function () {
            Route::get('units', [UnitApiController::class, 'index'])->name('units.index');
            Route::get('units/{unit}', [UnitApiController::class, 'show'])->whereNumber('unit')->name('units.show');
            Route::post('units', [UnitApiController::class, 'store'])->name('units.store');
            Route::post('units/{unit}', [UnitApiController::class, 'update'])->whereNumber('unit')->name('units.update');
            Route::delete('units/{unit}', [UnitApiController::class, 'destroy'])->whereNumber('unit')->name('units.destroy');
            Route::patch('units/reorder', [UnitApiController::class, 'reorder'])->name('units.reorder');
        });

        /*
         * Dashboard stats (Fase 7) — visible to anyone who reaches the
         * dashboard, same as the fixture it replaces.
         */
        Route::get('stats/{section}', [StatsApiController::class, 'show'])
            ->whereIn('section', ['summary', 'publishing_trend', 'status_breakdown', 'recent_activity', 'popular_news'])
            ->name('stats.show');

        /*
         * Riwayat Aktivitas (Fase 7) — tenant-scoped audit trail.
         */
        Route::middleware('permission:activity.view')->group(function () {
            Route::get('activity', [ActivityLogApiController::class, 'index'])->name('activity.index');
        });
    });
