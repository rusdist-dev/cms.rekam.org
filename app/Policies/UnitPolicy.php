<?php

namespace App\Policies;

use App\Models\Unit;
use App\Models\User;

class UnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('units.view');
    }

    public function view(User $user, Unit $unit): bool
    {
        return $user->can('units.view');
    }

    public function create(User $user): bool
    {
        return $user->can('units.create');
    }

    public function update(User $user, Unit $unit): bool
    {
        return $user->can('units.update');
    }

    public function delete(User $user, Unit $unit): bool
    {
        return $user->can('units.delete');
    }

    /** Class-string ability for the batch reorder endpoint. */
    public function reorder(User $user): bool
    {
        return $user->can('units.reorder');
    }
}
