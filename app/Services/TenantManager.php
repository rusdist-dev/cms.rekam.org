<?php

namespace App\Services;

use App\Exceptions\TenantNotResolvedException;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

/**
 * Single source of truth for "which company am I editing right now".
 *
 * Every feature check, menu entry, and content query goes through here, so
 * nothing else in the codebase ever needs to know a tenant slug or database
 * name (context.md §5.2, §5.8).
 */
class TenantManager
{
    public const SESSION_KEY = 'current_tenant_id';

    private ?Tenant $current = null;

    private ?Collection $accessible = null;

    /**
     * The connection's original .env-sourced credentials, captured once here
     * — never re-read from config() after that, since setCurrent() below
     * mutates the very same keys. Reading them back mid-request (e.g. inside
     * forEachTenant()'s loop) would leak whichever tenant's override happened
     * to be set last as if it were the shared default. Safe to capture in the
     * constructor because this class is bound as a singleton
     * (AppServiceProvider::register()), constructed once per request before
     * any setCurrent() call.
     */
    private readonly string $defaultTenantUsername;

    private readonly string $defaultTenantPassword;

    public function __construct()
    {
        $this->defaultTenantUsername = config('database.connections.tenant.username');
        $this->defaultTenantPassword = config('database.connections.tenant.password');
    }

    /**
     * Points the `tenant` connection at this company's database — and its own
     * credentials, for a tenant whose database lives on a host that locks
     * every database to its own dedicated user (docs/deploy.md). A tenant
     * without an override keeps using the shared .env credentials.
     *
     * The purge is not optional: without it Laravel keeps handing out the PDO
     * opened for the previous tenant, and a switch would silently keep reading
     * the old company's content.
     */
    public function setCurrent(Tenant $tenant): void
    {
        $this->current = $tenant;

        Config::set('database.connections.tenant.database', $tenant->db_name);
        Config::set('database.connections.tenant.username', $tenant->db_username ?: $this->defaultTenantUsername);
        Config::set('database.connections.tenant.password', $tenant->db_password ?: $this->defaultTenantPassword);

        DB::purge('tenant');
        DB::reconnect('tenant');
    }

    public function current(): ?Tenant
    {
        return $this->current;
    }

    /**
     * The active tenant, or a loud failure. Content queries must never fall
     * back to a default database (context.md §5.3).
     */
    public function currentOrFail(): Tenant
    {
        return $this->current ?? throw new TenantNotResolvedException;
    }

    public function hasTenant(): bool
    {
        return $this->current !== null;
    }

    public function currentId(): ?int
    {
        return $this->current?->id;
    }

    public function currentSlug(): ?string
    {
        return $this->current?->slug;
    }

    /** Tenants the signed-in user may switch to. */
    public function accessible(): Collection
    {
        if ($this->accessible !== null) {
            return $this->accessible;
        }

        $user = auth()->user();

        return $this->accessible = $user
            ? collect($user->accessibleTenants())
            : collect();
    }

    /**
     * Resolves the tenant for this request: the one remembered in the session
     * when the user still has access to it, otherwise their first company.
     */
    public function resolveForCurrentUser(): ?Tenant
    {
        $accessible = $this->accessible();

        if ($accessible->isEmpty()) {
            return null;
        }

        $remembered = Session::get(self::SESSION_KEY);

        // Access can be revoked between requests, so a remembered id is a hint,
        // never an authorisation.
        $tenant = $accessible->firstWhere('id', $remembered) ?? $accessible->first();

        if ($tenant->id !== $remembered) {
            Session::put(self::SESSION_KEY, $tenant->id);
        }

        $this->setCurrent($tenant);

        return $tenant;
    }

    /** Switches the active tenant, returning false when it is not available. */
    public function switchTo(int $tenantId): bool
    {
        $tenant = $this->accessible()->firstWhere('id', $tenantId);

        if (! $tenant) {
            return false;
        }

        Session::put(self::SESSION_KEY, $tenant->id);
        $this->setCurrent($tenant);

        return true;
    }

    /**
     * Re-reads the active tenant after it has been edited, so the rest of the
     * request sees the new flags rather than the ones loaded at its start.
     */
    public function refresh(): void
    {
        $this->accessible = null;

        if ($this->current) {
            $this->setCurrent($this->current->fresh());
        }
    }

    public function forget(): void
    {
        Session::forget(self::SESSION_KEY);
        $this->current = null;
        $this->accessible = null;
    }

    /**
     * The only sanctioned way to branch on tenant capability. Branching on the
     * slug instead is a review rejection (context.md §5.8).
     */
    public function hasFeature(string $feature): bool
    {
        return $this->current?->hasFeature($feature) ?? false;
    }

    public function features(): array
    {
        return $this->current?->featureMap() ?? [];
    }

    /** Tenants decorated for the topbar switcher. */
    public function forSwitcher(): array
    {
        $currentId = $this->currentId();

        return $this->accessible()
            ->map(fn (Tenant $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'domain' => $t->domain,
                'is_current' => $t->id === $currentId,
                'switch_url' => route('tenant.switch', $t->id),
            ])
            ->all();
    }

    /**
     * Runs a callback against another tenant, restoring the previous one after.
     *
     * Cross-tenant joins are forbidden, so aggregating over both companies means
     * looping here rather than querying across databases (context.md §5.4).
     */
    public function forEachTenant(callable $callback): array
    {
        $previous = $this->current;
        $results = [];

        foreach (Tenant::active()->orderBy('sort_order')->get() as $tenant) {
            $this->setCurrent($tenant);
            $results[$tenant->slug] = $callback($tenant);
        }

        if ($previous) {
            $this->setCurrent($previous);
        } else {
            $this->current = null;
        }

        return $results;
    }
}
