<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    public const SUPER_ADMIN = 'super-admin';

    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'is_active' => 'boolean',
        'password' => 'hashed',
    ];

    /** Companies this user may switch between (context.md §5.3). */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(self::SUPER_ADMIN);
    }

    /**
     * A super-admin reaches every company; everyone else only the ones assigned
     * to them. This is the one place that rule lives.
     */
    public function accessibleTenants()
    {
        if ($this->isSuperAdmin()) {
            return Tenant::active()->orderBy('sort_order')->orderBy('name')->get();
        }

        return $this->tenants()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function canAccessTenant(Tenant $tenant): bool
    {
        if (! $tenant->is_active) {
            return false;
        }

        return $this->isSuperAdmin() || $this->tenants()->whereKey($tenant->getKey())->exists();
    }
}
