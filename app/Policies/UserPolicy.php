<?php

namespace App\Policies;

use App\Models\User;

/**
 * Permissions decide *what* an account may do; this policy adds the guards that
 * permissions alone cannot express — chiefly, that nobody can lock the
 * organisation out of its own CMS.
 *
 * A super-admin short-circuits every check through Gate::before, so these rules
 * apply to admins managing other accounts.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    public function view(User $user, User $target): bool
    {
        return $user->can('users.view');
    }

    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    public function update(User $user, User $target): bool
    {
        if (! $user->can('users.update')) {
            return false;
        }

        // Only a super-admin may edit a super-admin; otherwise an admin could
        // demote the owner and take over.
        return ! $target->isSuperAdmin() || $user->isSuperAdmin();
    }

    public function delete(User $user, User $target): bool
    {
        if (! $user->can('users.delete')) {
            return false;
        }

        // Deleting yourself logs you out mid-request and is never intentional.
        if ($user->is($target)) {
            return false;
        }

        if ($target->isSuperAdmin() && ! $user->isSuperAdmin()) {
            return false;
        }

        // The last super-admin is the only way back into the system.
        return ! ($target->isSuperAdmin() && $this->superAdminCount() <= 1);
    }

    /** Deactivating has the same lock-out risk as deleting. */
    public function deactivate(User $user, User $target): bool
    {
        return $this->delete($user, $target);
    }

    private function superAdminCount(): int
    {
        return User::role(User::SUPER_ADMIN)->where('is_active', true)->count();
    }
}
