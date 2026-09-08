<?php

use App\Http\Controllers\Dashboard\ActivityLogController;
use App\Http\Controllers\Dashboard\ContactMessageController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\EventController;
use App\Http\Controllers\Dashboard\MilestoneController;
use App\Http\Controllers\Dashboard\NewsController;
use App\Http\Controllers\Dashboard\PartnerController;
use App\Http\Controllers\Dashboard\PublicationController;
use App\Http\Controllers\Dashboard\RoleController;
use App\Http\Controllers\Dashboard\SiteSettingController;
use App\Http\Controllers\Dashboard\TeamMemberController;
use App\Http\Controllers\Dashboard\TenantController;
use App\Http\Controllers\Dashboard\TenantSwitchController;
use App\Http\Controllers\Dashboard\UnitController;
use App\Http\Controllers\Dashboard\UserController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — Blade pages only
|--------------------------------------------------------------------------
|
| These controllers render the HTML shell. Table rows, detail records and chart
| data are fetched by Alpine from routes/dash-api.php (context.md §4.1), so
| nothing here may pass a paginated collection to a view.
|
*/

Route::redirect('/', '/dashboard')->name('home');

// `tenant` binds the tenant database connection for the whole group; without
// it a content query has nowhere to read from (context.md §5.3).
Route::middleware(['auth', 'active', 'tenant'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::put('/tenant/{tenant}/switch', TenantSwitchController::class)->name('tenant.switch');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    /*
     * Content modules. `create` and `edit` share one form view — the difference
     * is which record Alpine loads into it.
     */
    Route::middleware(['feature:news', 'permission:news.view'])->group(function () {
        Route::get('/berita', [NewsController::class, 'index'])->name('news.index');
        Route::get('/berita/tambah', [NewsController::class, 'create'])->name('news.create');
        Route::get('/berita/{news}/ubah', [NewsController::class, 'edit'])->name('news.edit');
    });

    Route::middleware(['feature:events', 'permission:events.view'])->group(function () {
        Route::get('/events', [EventController::class, 'index'])->name('events.index');
        Route::get('/events/tambah', [EventController::class, 'create'])->name('events.create');
        Route::get('/events/{event}/ubah', [EventController::class, 'edit'])->name('events.edit');
    });

    Route::middleware(['feature:team', 'permission:team.view'])->group(function () {
        Route::get('/tim', [TeamMemberController::class, 'index'])->name('team.index');
        Route::get('/tim/tambah', [TeamMemberController::class, 'create'])->name('team.create');
        Route::get('/tim/{member}/ubah', [TeamMemberController::class, 'edit'])->name('team.edit');
    });

    Route::middleware(['feature:publications', 'permission:publications.view'])->group(function () {
        Route::get('/publikasi', [PublicationController::class, 'index'])->name('publications.index');
        Route::get('/publikasi/tambah', [PublicationController::class, 'create'])->name('publications.create');
        Route::get('/publikasi/{publication}/ubah', [PublicationController::class, 'edit'])->name('publications.edit');
    });

    Route::middleware(['feature:partners', 'permission:partners.view'])->group(function () {
        Route::get('/partner', [PartnerController::class, 'index'])->name('partners.index');
        Route::get('/partner/tambah', [PartnerController::class, 'create'])->name('partners.create');
        Route::get('/partner/{partner}/ubah', [PartnerController::class, 'edit'])->name('partners.edit');
    });

    Route::middleware(['feature:contacts', 'permission:contacts.view'])->group(function () {
        Route::get('/kontak', [ContactMessageController::class, 'index'])->name('contacts.index');
        Route::get('/kontak/pengaturan', [ContactMessageController::class, 'settings'])->name('contacts.settings');
        Route::get('/kontak/{message}', [ContactMessageController::class, 'show'])->name('contacts.show');
    });

    // Tenant-specific modules: absent from the menu, the router and the public
    // API when the flag is off (context.md §5.6).
    Route::middleware(['feature:milestones', 'permission:milestones.view'])->group(function () {
        Route::get('/milestone', [MilestoneController::class, 'index'])->name('milestones.index');
        Route::get('/milestone/tambah', [MilestoneController::class, 'create'])->name('milestones.create');
        Route::get('/milestone/{milestone}/ubah', [MilestoneController::class, 'edit'])->name('milestones.edit');
    });

    Route::middleware(['feature:units', 'permission:units.view'])->group(function () {
        Route::get('/unit', [UnitController::class, 'index'])->name('units.index');
        Route::get('/unit/tambah', [UnitController::class, 'create'])->name('units.create');
        Route::get('/unit/{unit}/ubah', [UnitController::class, 'edit'])->name('units.edit');
    });

    /*
     * System modules.
     */
    Route::get('/pengaturan', SiteSettingController::class)
        ->middleware('permission:settings.view')->name('settings.index');

    Route::middleware('permission:tenants.view')->group(function () {
        Route::get('/company', [TenantController::class, 'index'])->name('tenants.index');
        Route::get('/company/{tenant}/ubah', [TenantController::class, 'edit'])->name('tenants.edit');
    });

    Route::middleware('permission:users.view')->group(function () {
        Route::get('/pengguna', [UserController::class, 'index'])->name('users.index');
        Route::get('/pengguna/tambah', [UserController::class, 'create'])->name('users.create');
        Route::get('/pengguna/{user}/ubah', [UserController::class, 'edit'])->name('users.edit');
    });

    Route::middleware('permission:roles.view')->group(function () {
        Route::get('/peran', [RoleController::class, 'index'])->name('roles.index');
        Route::get('/peran/tambah', [RoleController::class, 'create'])->name('roles.create');
        Route::get('/peran/{role}/ubah', [RoleController::class, 'edit'])->name('roles.edit');
    });

    Route::get('/riwayat-aktivitas', [ActivityLogController::class, 'index'])
        ->middleware('permission:activity.view')->name('activity.index');
});

require __DIR__.'/auth.php';
