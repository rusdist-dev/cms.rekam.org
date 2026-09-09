<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * A company whose content this CMS manages. Lives on the central connection —
 * the registry cannot itself be tenant-scoped (context.md §5.1).
 */
class Tenant extends Model
{
    use HasFactory;

    // Explicit; $guarded = [] is forbidden (context.md §8.5).
    protected $fillable = [
        'name',
        'slug',
        'db_name',
        'domain',
        'features',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'features' => 'array',
        'is_active' => 'boolean',
        'api_key_generated_at' => 'datetime',
        // Encrypted at rest with APP_KEY — same protection as everything else
        // central. db_username is not secret by itself but is kept out of
        // responses anyway; there is no caller that needs it.
        'db_password' => 'encrypted',
    ];

    // The hash is never useful to a caller and must not leak into a response.
    protected $hidden = ['api_key', 'db_username', 'db_password'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    /**
     * The only sanctioned way to branch on tenant capability — branching on the
     * slug instead is a review rejection (context.md §5.8).
     */
    public function hasFeature(string $feature): bool
    {
        // A feature marked core in config is on for every tenant, so a bad
        // features payload can never switch off the CMS's own backbone.
        if (config("cms.features.{$feature}.core") === true) {
            return true;
        }

        return (bool) ($this->features[$feature] ?? false);
    }

    /** Feature map filled out from config, so a missing key reads as false. */
    public function featureMap(): array
    {
        $stored = $this->features ?? [];

        return collect(config('cms.features'))
            ->map(fn (array $meta, string $key) => $meta['core']
                ? true
                : (bool) ($stored[$key] ?? false))
            ->all();
    }

    /** Replaces the flags, ignoring unknown keys and forcing core ones on. */
    public function syncFeatures(array $features): void
    {
        $this->features = collect(config('cms.features'))
            ->map(fn (array $meta, string $key) => $meta['core']
                ? true
                : (bool) ($features[$key] ?? false))
            ->all();

        $this->save();
    }

    /**
     * Issues a new key and returns the plaintext once. The stored value is a
     * hash, so a leaked database dump does not hand over API access.
     */
    public function rotateApiKey(): string
    {
        $plain = $this->slug.'_'.Str::random(48);

        $this->forceFill([
            'api_key' => Hash::make($plain),
            'api_key_generated_at' => now(),
        ])->save();

        return $plain;
    }

    public function matchesApiKey(string $plain): bool
    {
        return $this->api_key !== null && Hash::check($plain, $this->api_key);
    }

    /**
     * Overrides the shared .env tenant-connection credentials for this one
     * database — for hosts that lock every database to its own dedicated
     * user (plan.md/docs/deploy.md). Pass both null to revert to the shared
     * credentials. Never mass-assignable: set only from the console
     * (`php artisan tenant:db-credentials`), never through a form.
     */
    public function setDatabaseCredentials(?string $username, ?string $password): void
    {
        $this->forceFill([
            'db_username' => $username,
            'db_password' => $password,
        ])->save();
    }

    /**
     * Resolves a submitted plaintext key to its tenant, for the public API.
     *
     * The key is always `{slug}_{random}` (see rotateApiKey()), so this is a
     * single indexed lookup by slug plus one bcrypt check — not a loop over
     * every tenant hashing the same key against each one.
     */
    public static function findByApiKey(string $plain): ?self
    {
        $slug = Str::before($plain, '_');

        if ($slug === $plain) {
            // No underscore at all — not a key this app ever issued.
            return null;
        }

        $tenant = static::active()->where('slug', $slug)->first();

        return $tenant && $tenant->matchesApiKey($plain) ? $tenant : null;
    }

    /** Database name derived from the slug, used when provisioning a tenant. */
    public static function databaseNameFor(string $slug): string
    {
        return config('cms.tenant_db_prefix', env('DB_TENANT_PREFIX', 'cms_')).$slug;
    }
}
