<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

/**
 * Tenants are provisioned from the console (a new database has to exist first),
 * so the CMS only ever reads and edits them — never creates or deletes.
 */
class TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tenants.view');
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return $user->can('tenants.view');
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $user->can('tenants.update');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        // Deleting the registry row would orphan an entire content database.
        return false;
    }

    public function rotateApiKey(User $user, Tenant $tenant): bool
    {
        return $user->can('tenants.update');
    }
}
