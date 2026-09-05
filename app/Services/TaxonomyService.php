<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Reads option lists out of the active tenant's site_settings.
 *
 * Nothing here knows which company it is serving: the same call returns rekam's
 * five team levels or perikanan's three, because the options are data
 * (context.md §5.12). Adding a programme is an editor's job, not a deploy.
 */
class TaxonomyService
{
    /** UI group name => [settings group, settings key]. */
    private const GROUPS = [
        'team_levels' => ['team', 'levels'],
        'news_programs' => ['news', 'programs'],
        'event_categories' => ['event', 'categories'],
        'publication_categories' => ['pub', 'categories'],
    ];

    public function __construct(private readonly TenantManager $tenants) {}

    public static function groups(): array
    {
        return array_keys(self::GROUPS);
    }

    public static function isKnown(string $group): bool
    {
        return array_key_exists($group, self::GROUPS);
    }

    /**
     * Options for a group, flattened to the shape the front-end selects use.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function options(string $group, ?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $fallback = config('cms.fallback_locale');

        return collect($this->raw($group))
            ->map(fn (array $option) => [
                'value' => $option['slug'] ?? '',
                // EN is optional everywhere, so an empty translation falls back
                // rather than rendering a blank option (context.md §6.4).
                'label' => $option['label'][$locale]
                    ?: ($option['label'][$fallback] ?? $option['slug'] ?? ''),
            ])
            ->filter(fn (array $o) => $o['value'] !== '')
            ->values()
            ->all();
    }

    /** Raw stored options, including every translation. */
    public function raw(string $group): array
    {
        if (! self::isKnown($group)) {
            return [];
        }

        [$settingsGroup, $key] = self::GROUPS[$group];

        return $this->cache()->remember(
            $this->cacheKey($group),
            now()->addMinutes(30),
            fn () => SiteSetting::get($settingsGroup, $key, []) ?? []
        );
    }

    /** Label for one slug, used when rendering stored content. */
    public function label(string $group, string $slug, ?string $locale = null): ?string
    {
        return collect($this->options($group, $locale))->firstWhere('value', $slug)['label'] ?? null;
    }

    public function replace(string $group, array $options): void
    {
        if (! self::isKnown($group)) {
            return;
        }

        [$settingsGroup, $key] = self::GROUPS[$group];

        SiteSetting::put($settingsGroup, $key, array_values($options));

        $this->flush($group);
    }

    public function flush(?string $group = null): void
    {
        foreach ($group ? [$group] : self::groups() as $name) {
            $this->cache()->forget($this->cacheKey($name));
        }
    }

    /**
     * Tags would be tidier, but the file and database cache stores do not
     * support them — and requiring Redis just for taxonomy is not worth it.
     * Tenant isolation comes from the key instead, which every driver honours
     * (context.md §5.10).
     */
    private function cache(): \Illuminate\Contracts\Cache\Repository
    {
        $store = Cache::store();

        return $store->supportsTags()
            ? $store->tags(['tenant:'.$this->tenants->currentId(), 'taxonomy'])
            : $store;
    }

    /**
     * Cache keys carry the tenant id — a shared key would serve one company's
     * programmes to the other (context.md §5.10).
     */
    private function cacheKey(string $group): string
    {
        return 'taxonomy:'.$this->tenants->currentOrFail()->id.':'.$group;
    }
}
