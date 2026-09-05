<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * Row links point at ids Alpine only knows at render time, but literal URLs in
 * Blade or JS are forbidden (context.md §8.2). This generates the URL from the
 * named route with a placeholder the view substitutes:
 *
 *   :href="'{{ RouteTemplate::for('news.edit', 'news') }}'.replace('__ID__', item.id)"
 *
 * so renaming the route still updates every link.
 */
class RouteTemplate
{
    public const PLACEHOLDER = '__ID__';

    public static function for(string $name, string $parameter = 'id', array $extra = []): string
    {
        if (! Route::has($name)) {
            return '#';
        }

        return route($name, [$parameter => self::PLACEHOLDER, ...$extra]);
    }
}
