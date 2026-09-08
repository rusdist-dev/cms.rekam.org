<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Per-locale slugs for translatable content (plan.md §2.3).
 *
 * Uniqueness is checked per locale inside the JSON column, because two articles
 * may legitimately share an English slug while differing in Indonesian.
 */
class SlugMaker
{
    /**
     * Builds the slug map from the submitted slugs, falling back to the title
     * for any locale the editor left blank.
     *
     * @param  array<string, string|null>  $slugs
     * @param  array<string, string|null>  $titles
     */
    public static function forTranslatable(
        string $modelClass,
        array $slugs,
        array $titles,
        ?int $ignoreId = null,
        string $column = 'slug',
    ): array {
        $out = [];

        foreach (config('cms.locales') as $locale) {
            $source = $slugs[$locale] ?? null;

            if (! is_string($source) || trim($source) === '') {
                $source = $titles[$locale] ?? null;
            }

            // A locale with neither slug nor title stays null rather than
            // becoming a meaningless "n" from an empty Str::slug.
            if (! is_string($source) || trim($source) === '') {
                $out[$locale] = null;

                continue;
            }

            $out[$locale] = self::unique($modelClass, Str::slug($source), $locale, $ignoreId, $column);
        }

        return $out;
    }

    /** Appends -2, -3, … until the slug is free for that locale. */
    public static function unique(
        string $modelClass,
        string $slug,
        string $locale,
        ?int $ignoreId = null,
        string $column = 'slug',
    ): string {
        $base = $slug !== '' ? $slug : 'item';
        $candidate = $base;
        $suffix = 1;

        while (self::taken($modelClass, $candidate, $locale, $ignoreId, $column)) {
            $suffix++;
            $candidate = "{$base}-{$suffix}";
        }

        return $candidate;
    }

    private static function taken(
        string $modelClass,
        string $slug,
        string $locale,
        ?int $ignoreId,
        string $column,
    ): bool {
        /** @var Model $modelClass */
        $query = $modelClass::query()->where("{$column}->{$locale}", $slug);

        // Soft-deleted rows still own their slug: restoring one must not collide.
        if (method_exists($modelClass, 'bootSoftDeletes')) {
            $query->withTrashed();
        }

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->exists();
    }

    /**
     * Same suffixing behaviour as {@see forTranslatable()}, for a model whose
     * slug is a single plain column rather than a per-locale JSON one (a
     * person's name has no ID/EN version to key off).
     */
    public static function uniquePlain(
        string $modelClass,
        string $source,
        ?int $ignoreId = null,
        string $column = 'slug',
    ): string {
        $base = Str::slug($source);
        $base = $base !== '' ? $base : 'item';
        $candidate = $base;
        $suffix = 1;

        while (self::takenPlain($modelClass, $candidate, $ignoreId, $column)) {
            $suffix++;
            $candidate = "{$base}-{$suffix}";
        }

        return $candidate;
    }

    private static function takenPlain(
        string $modelClass,
        string $slug,
        ?int $ignoreId,
        string $column,
    ): bool {
        /** @var Model $modelClass */
        $query = $modelClass::query()->where($column, $slug);

        if (method_exists($modelClass, 'bootSoftDeletes')) {
            $query->withTrashed();
        }

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->exists();
    }
}
