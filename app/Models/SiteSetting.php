<?php

namespace App\Models;

use App\Models\Concerns\TenantModel;

/**
 * Per-tenant settings and taxonomy, stored as data rather than code.
 *
 * This is what lets rekam and perikanan run the same tables with different team
 * levels, categories and programs — and lets an editor add a program without a
 * deploy (context.md §5.12).
 */
class SiteSetting extends TenantModel
{
    protected $table = 'site_settings';

    protected $fillable = ['group', 'key', 'value'];

    protected $casts = ['value' => 'array'];

    /** Reads one setting, or the fallback when it has never been written. */
    public static function get(string $group, string $key, mixed $default = null): mixed
    {
        return static::where('group', $group)->where('key', $key)->first()?->value ?? $default;
    }

    public static function put(string $group, string $key, mixed $value): self
    {
        return static::updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => $value]
        );
    }

    /** All settings in a group, keyed by setting name. */
    public static function group(string $group): array
    {
        return static::where('group', $group)->pluck('value', 'key')->all();
    }
}
