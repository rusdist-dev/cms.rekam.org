<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Roles define who can do what, so editing them is the most privileged action
 * in the CMS. `super-admin` is deliberately immutable: it passes every gate via
 * Gate::before, so editing its permission list would change nothing while
 * appearing to work.
 */
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can('roles.view');
    }

    public function create(User $user): bool
    {
        return $user->can('roles.create');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can('roles.update') && $role->name !== User::SUPER_ADMIN;
    }

    public function delete(User $user, Role $role): bool
    {
        if (! $user->can('roles.delete') || $role->name === User::SUPER_ADMIN) {
            return false;
        }

        // A role still in use would silently strip permissions from everyone
        // holding it.
        return $role->users()->count() === 0;
    }
}
