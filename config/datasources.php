<?php

return [

    /*
    |--------------------------------------------------------------------------
    | External Datasources
    |--------------------------------------------------------------------------
    |
    | Databases that belong to *another* system — not the central CMS database
    | (`mysql`) and not a tenant content database (`tenant`). Each entry here is
    | registered as a real Laravel connection at boot by DatasourceServiceProvider,
    | named `{prefix}{key}` (so `simrs` becomes the `ds_simrs` connection).
    |
    | The whole point of routing these through one registry instead of pasting
    | more blocks into config/database.php is that "which databases does this app
    | touch, and which of them may it write to" stays answerable from a single
    | file — and that a read-only source is enforced, not just intended.
    |
    | Adding one is four steps, no application code required for the plumbing:
    |
    |   1. add the credentials to .env (see .env.example);
    |   2. add an entry below;
    |   3. run `php artisan cms:datasources` to confirm it connects;
    |   4. add a model (App\Models\External\*) and routes (routes/ext-api.php).
    |
    | docs/api-external.md walks through the whole thing.
    |
    */

    /*
    | Prefixing keeps a datasource from ever shadowing `mysql`, `tenant`, or any
    | other first-party connection — a source keyed `tenant` would otherwise
    | silently repoint every content model at someone else's database.
    */
    'prefix' => 'ds_',

    /*
    | Merged under every source's own `connection` block, so an entry below only
    | has to state what makes it different. Anything here can be overridden
    | per-source (a datasource on Postgres just sets its own `driver`/`port`).
    */
    'defaults' => [
        'driver' => 'mysql',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'prefix_indexes' => true,
        // Off by default: these schemas are owned by another team and routinely
        // predate strict mode. Turning it on would make ordinary SELECTs fail
        // on zero dates and the like, which is not ours to fix.
        'strict' => false,
        'engine' => null,
    ],

    /*
    | Response cache TTL (seconds) and per-IP rate limit for /api/v1/ext/*.
    | Separate from cms.public_api because external systems are usually the
    | slower, more fragile dependency — cache them harder, call them less.
    */
    'cache_ttl' => (int) env('DS_CACHE_TTL', 300),
    'rate_limit' => (int) env('DS_RATE_LIMIT', 120),

    /*
    |--------------------------------------------------------------------------
    | Sources
    |--------------------------------------------------------------------------
    |
    | key => [
    |     'label'      => shown in errors and `php artisan cms:datasources`,
    |     'read_only'  => true blocks every non-SELECT at the connection level,
    |     'connection' => merged over `defaults` above,
    | ]
    |
    | A source whose `database` resolves to empty counts as *not configured*:
    | it is still registered, but the `datasource:` middleware answers 503 and
    | its models throw DatasourceNotConfiguredException rather than connecting
    | to whatever the driver would have defaulted to. That is deliberate — a
    | missing .env line must fail by name, not by hitting the wrong database.
    |
    | Template (copy, rename, uncomment):
    |
    |   'simrs' => [
    |       'label' => 'SIMRS',
    |       'read_only' => true,
    |       'connection' => [
    |           'host' => env('DS_SIMRS_HOST', '127.0.0.1'),
    |           'port' => env('DS_SIMRS_PORT', '3306'),
    |           'database' => env('DS_SIMRS_DATABASE'),
    |           'username' => env('DS_SIMRS_USERNAME'),
    |           'password' => env('DS_SIMRS_PASSWORD'),
    |       ],
    |   ],
    |
    */

    'sources' => [
        'coast' => [
            'label' => 'COAST',
            'read_only' => true,
            'connection' => [
                'host' => env('DS_COAST_HOST', '127.0.0.1'),
                'port' => env('DS_COAST_PORT', '3306'),
                'database' => env('DS_COAST_DATABASE'),
                'username' => env('DS_COAST_USERNAME'),
                'password' => env('DS_COAST_PASSWORD'),
            ],
        ],
        'ikan' => [
            'label' => 'IKAN',
            'read_only' => true,
            'connection' => [
                'host' => env('DS_IKAN_HOST', '127.0.0.1'),
                'port' => env('DS_IKAN_PORT', '3306'),
                'database' => env('DS_IKAN_DATABASE'),
                'username' => env('DS_IKAN_USERNAME'),
                'password' => env('DS_IKAN_PASSWORD'),
            ],
        ],
        'hiupari' => [
            'label' => 'HIUPARI',
            'read_only' => true,
            'connection' => [
                'host' => env('DS_HIUPARI_HOST', '127.0.0.1'),
                'port' => env('DS_HIUPARI_PORT', '3306'),
                'database' => env('DS_HIUPARI_DATABASE'),
                'username' => env('DS_HIUPARI_USERNAME'),
                'password' => env('DS_HIUPARI_PASSWORD'),
            ],
        ],
        'bsc' => [
            'label' => 'BSC',
            'read_only' => true,
            'connection' => [
                'host' => env('DS_BSC_HOST', '127.0.0.1'),
                'port' => env('DS_BSC_PORT', '3306'),
                'database' => env('DS_BSC_DATABASE'),
                'username' => env('DS_BSC_USERNAME'),
                'password' => env('DS_BSC_PASSWORD'),
            ],
        ],
        'stsc' => [
            'label' => 'STSC',
            'read_only' => true,
            'connection' => [
                'host' => env('DS_STSC_HOST', '127.0.0.1'),
                'port' => env('DS_STSC_PORT', '3306'),
                'database' => env('DS_STSC_DATABASE'),
                'username' => env('DS_STSC_USERNAME'),
                'password' => env('DS_STSC_PASSWORD'),
            ],
        ],
        'jogolaut' => [
            'label' => 'JOGOLAUT',
            'read_only' => true,
            'connection' => [
                'host' => env('DS_JOGOLAUT_HOST', '127.0.0.1'),
                'port' => env('DS_JOGOLAUT_PORT', '3306'),
                'database' => env('DS_JOGOLAUT_DATABASE'),
                'username' => env('DS_JOGOLAUT_USERNAME'),
                'password' => env('DS_JOGOLAUT_PASSWORD'),
                // Connect timeout, seconds. Without it an unreachable host
                // holds every request for the driver default (often a
                // minute) — and the monitoring payload reads seven tables.
                'options' => [
                    PDO::ATTR_TIMEOUT => (int) env('DS_JOGOLAUT_TIMEOUT', 5),
                ],
            ],
        ],
    ],

];
