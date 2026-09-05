<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Content Locales
    |--------------------------------------------------------------------------
    |
    | Content is bilingual and stored in translatable JSON columns. The CMS UI
    | itself is Indonesian only (context.md §6.5) — these locales describe the
    | *content*, not the interface. `fallback` is used by the public API when a
    | translation is empty.
    |
    */

    'locales' => ['id', 'en'],
    'default_locale' => 'id',
    'fallback_locale' => 'id',

    'locale_labels' => [
        'id' => 'Indonesia',
        'en' => 'Inggris',
    ],

    /*
    |--------------------------------------------------------------------------
    | Publishing Statuses
    |--------------------------------------------------------------------------
    |
    | Shared by news and events. Only `published` content is exposed through the
    | public API (context.md §4.10).
    |
    */

    'statuses' => [
        'draft' => 'Draf',
        'scheduled' => 'Terjadwal',
        'published' => 'Terbit',
    ],

    'public_status' => 'published',

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | The canonical list of modules a tenant may switch on or off. The per-tenant
    | value lives in `tenants.features` (JSON); this list defines which keys are
    | valid, how they are labelled in the tenant management screen, and what a
    | newly created tenant starts with.
    |
    | `core` features are always on and cannot be toggled off in the UI.
    |
    */

    'features' => [
        'news' => ['label' => 'Berita', 'default' => true, 'core' => true],
        'news_programs' => ['label' => 'Program Berita', 'default' => true, 'core' => false],
        'events' => ['label' => 'Events', 'default' => true, 'core' => false],
        'event_rundown' => ['label' => 'Rundown Event', 'default' => false, 'core' => false],
        'team' => ['label' => 'Tim', 'default' => true, 'core' => false],
        'publications' => ['label' => 'Publikasi', 'default' => false, 'core' => false],
        'partners' => ['label' => 'Partner', 'default' => true, 'core' => false],
        'contacts' => ['label' => 'Kontak', 'default' => true, 'core' => false],
        'milestones' => ['label' => 'Milestone', 'default' => false, 'core' => false],
        'units' => ['label' => 'Unit', 'default' => false, 'core' => false],
    ],

    /*
    |--------------------------------------------------------------------------
    | Media & Uploads
    |--------------------------------------------------------------------------
    |
    | Files are stored per tenant under `media/{tenant_slug}/...` on the public
    | disk (context.md §5.9). Sizes are in kilobytes to match Laravel's `max:`
    | validation rule.
    |
    */

    'media' => [
        'disk' => 'public',
        'path_prefix' => 'media',

        'image' => [
            'max_kb' => 4096,
            'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
            'thumbnail' => ['width' => 400, 'height' => 400],
            'max_width' => 1920,
        ],

        'document' => [
            'max_kb' => 20480,
            'mimes' => ['pdf'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */

    'per_page' => 15,
    'per_page_options' => [15, 25, 50, 100],
    'max_per_page' => 100,

    /*
    |--------------------------------------------------------------------------
    | Public API
    |--------------------------------------------------------------------------
    |
    | CORS is restricted to the compro domains — never `*` (plan.md §6, Fase 6).
    |
    */

    'public_api' => [
        'cache_ttl' => 300,
        'rate_limit' => 60,
        'contact_rate_limit' => 5,
        'allowed_origins' => [
            'https://rekam.org',
            'https://www.rekam.org',
            'https://perikanan.org',
            'https://www.perikanan.org',
        ],
    ],

];
