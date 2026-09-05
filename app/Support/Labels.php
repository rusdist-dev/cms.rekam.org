<?php

namespace App\Support;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * Human labels for role and permission slugs.
 *
 * Roles created in the CMS have no translation entry, so an unknown slug
 * degrades to a readable headline rather than showing the raw key.
 */
class Labels
{
    public static function role(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        return Lang::has("roles.{$name}")
            ? __("roles.{$name}")
            : Str::headline($name);
    }

    /** `news.publish` => "Berita · Terbitkan". */
    public static function permission(string $name): string
    {
        [$module, $action] = array_pad(explode('.', $name, 2), 2, '');

        return self::module($module).' · '.self::action($action);
    }

    public static function module(string $module): string
    {
        return Lang::has("permissions.modules.{$module}")
            ? __("permissions.modules.{$module}")
            : Str::headline($module);
    }

    public static function action(string $action): string
    {
        return Lang::has("permissions.actions.{$action}")
            ? __("permissions.actions.{$action}")
            : Str::headline($action);
    }
}
