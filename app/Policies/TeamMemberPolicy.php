<?php

namespace App\Policies;

use App\Models\TeamMember;
use App\Models\User;

class TeamMemberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('team.view');
    }

    public function view(User $user, TeamMember $team): bool
    {
        return $user->can('team.view');
    }

    public function create(User $user): bool
    {
        return $user->can('team.create');
    }

    public function update(User $user, TeamMember $team): bool
    {
        return $user->can('team.update');
    }

    public function delete(User $user, TeamMember $team): bool
    {
        return $user->can('team.delete');
    }

    /** Class-string ability for the batch reorder endpoint. */
    public function reorder(User $user): bool
    {
        return $user->can('team.reorder');
    }
}
