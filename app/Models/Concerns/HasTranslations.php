<?php

namespace App\Models\Concerns;

/**
 * Translatable JSON columns (plan.md §2.3): one row holds every locale, so a
 * form submits once and no join is needed to render content.
 *
 * The model keeps the full map; flattening to a single locale is the API
 * Resource's job, so nothing loses a translation on the way through.
 */
trait HasTranslations
{
    /** @return array<int, string> Columns stored as {"id": "...", "en": "..."} */
    public function translatable(): array
    {
        return $this->translatable ?? [];
    }

    /**
     * One locale's value, falling back to the default when the translation is
     * empty. EN is optional everywhere (context.md §6.4), so a missing
     * translation must render the Indonesian text rather than a blank.
     */
    public function trans(string $attribute, ?string $locale = null): ?string
    {
        $value = $this->getAttribute($attribute);

        return is_array($value) ? static::flatten($value, $locale) : $value;
    }

    /**
     * The same flatten-with-fallback rule as trans(), for a raw {id,en} map
     * that isn't a model attribute — e.g. SiteSetting::get()'s plain arrays,
     * read by the public API's settings endpoint (plan.md Fase 6).
     */
    public static function flatten(?array $value, ?string $locale = null): ?string
    {
        if (! is_array($value)) {
            return null;
        }

        $locale ??= app()->getLocale();
        $translated = $value[$locale] ?? null;

        if (is_string($translated) && trim($translated) !== '') {
            return $translated;
        }

        $fallback = $value[config('cms.fallback_locale')] ?? null;

        return is_string($fallback) && trim($fallback) !== '' ? $fallback : null;
    }

    /** Which locales have a usable value for every given column. */
    public function translationCompleteness(array $attributes): array
    {
        $result = [];

        foreach (config('cms.locales') as $locale) {
            $result[$locale] = collect($attributes)->every(function (string $attribute) use ($locale) {
                $value = $this->getAttribute($attribute);

                return is_array($value)
                    && is_string($value[$locale] ?? null)
                    && trim($value[$locale]) !== '';
            });
        }

        return $result;
    }

    /**
     * Normalises a submitted translatable value: every configured locale is
     * present, blanks become null, so `title->>'$.en'` is never an empty string
     * masquerading as a translation.
     */
    public static function normaliseTranslatable(mixed $value): array
    {
        $value = is_array($value) ? $value : [config('cms.default_locale') => $value];

        $out = [];

        foreach (config('cms.locales') as $locale) {
            $text = $value[$locale] ?? null;
            $text = is_string($text) ? trim($text) : $text;

            $out[$locale] = ($text === '' || $text === null) ? null : $text;
        }

        return $out;
    }
}
