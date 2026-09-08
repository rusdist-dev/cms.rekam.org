<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/dashboard';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // The public API (routes/api.php) is never called by an authenticated
        // user, so these key by IP alone — separate from 'api' above, which
        // exists for the internal Sanctum-oriented default route.
        RateLimiter::for('public-api', function (Request $request) {
            return Limit::perMinute(config('cms.public_api.rate_limit'))->by($request->ip());
        });

        // POST contact is the only public write in the system and the one
        // spam/abuse actually targets, so it gets its own, much stricter limit
        // (plan.md Fase 6: "throttle + honeypot").
        RateLimiter::for('public-contact', function (Request $request) {
            return Limit::perMinute(config('cms.public_api.contact_rate_limit'))->by($request->ip());
        });

        $this->routes(function () {
            // Public API for the compro sites: X-Api-Key, no session. Bare
            // group — like dash-api.php below, it declares its own complete
            // middleware stack (throttle, tenant/locale resolution) rather
            // than inheriting the Kernel's generic 'api' group, whose
            // hardcoded throttle would otherwise stack redundantly with the
            // dedicated public-api/public-contact limiters it defines.
            Route::middleware([])
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            // Internal API for Alpine. It declares its own `web` middleware and
            // prefix so the two API surfaces never share a guard by accident
            // (context.md §2.4).
            Route::group([], base_path('routes/dash-api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
