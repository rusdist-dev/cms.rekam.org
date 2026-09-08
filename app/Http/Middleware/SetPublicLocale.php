<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * `?lang=` picks the locale the public API flattens translatable fields to
 * (HasTranslations::trans() reads app()->getLocale() when none is passed
 * explicitly). An unknown or missing value falls back to the configured
 * default rather than erroring — a typo in a query string should never break
 * the compro site (plan.md Fase 6).
 */
class SetPublicLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $lang = $request->query('lang');

        $locale = in_array($lang, config('cms.locales'), true)
            ? $lang
            : config('cms.default_locale');

        App::setLocale($locale);

        return $next($request);
    }
}
