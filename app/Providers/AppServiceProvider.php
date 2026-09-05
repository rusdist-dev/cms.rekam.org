<?php

namespace App\Providers;

use App\Models\User;
use App\Services\TenantManager;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One resolved tenant per request — every feature check and content
        // query must agree on which company is active (context.md §5.3).
        $this->app->singleton(TenantManager::class);
    }

    public function boot(): void
    {
        $this->registerSuperAdminGate();
        $this->registerFeatureDirective();
        $this->shareLayoutChrome();
    }

    /**
     * A super-admin passes every *permission* check, so a newly added permission
     * never locks the owner out of their own CMS before the seeder runs.
     *
     * Policy abilities are deliberately excluded. Blanket-approving those would
     * also bypass the guards that keep the CMS usable at all — deleting your own
     * account mid-session, or removing the last super-admin. Permissions are
     * named `{module}.{action}`; policy abilities are bare verbs, so the dot
     * tells the two apart.
     */
    private function registerSuperAdminGate(): void
    {
        Gate::before(function (User $user, string $ability) {
            if (! str_contains($ability, '.')) {
                return null;
            }

            return $user->hasRole(User::SUPER_ADMIN) ? true : null;
        });
    }

    /**
     * `@feature('units') ... @endfeature` — the menu-level counterpart of the
     * `feature:` route middleware (context.md §5.6).
     */
    private function registerFeatureDirective(): void
    {
        Blade::if('feature', fn (string $feature) => app(TenantManager::class)->hasFeature($feature));
    }

    /**
     * The sidebar and topbar are on every dashboard page, so their data comes
     * from here rather than from each controller. This is chrome — constants
     * and the active tenant, never paginated content (context.md §4.2).
     */
    private function shareLayoutChrome(): void
    {
        View::composer('layouts.app', function ($view) {
            $tenants = app(TenantManager::class);

            $view->with([
                'chromeTenants' => $tenants->hasTenant() ? $tenants->forSwitcher() : [],
                'chromeUser' => $this->currentUserChrome(),
            ]);
        });
    }

    private function currentUserChrome(): array
    {
        $user = auth()->user();

        if (! $user) {
            return ['name' => 'Tamu'];
        }

        return [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->roles->first()?->name,
            'avatar' => $user->avatar,
        ];
    }
}
