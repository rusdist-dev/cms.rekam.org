<?php

namespace App\Policies;

use App\Models\Milestone;
use App\Models\User;

class MilestonePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('milestones.view');
    }

    public function view(User $user, Milestone $milestone): bool
    {
        return $user->can('milestones.view');
    }

    public function create(User $user): bool
    {
        return $user->can('milestones.create');
    }

    public function update(User $user, Milestone $milestone): bool
    {
        return $user->can('milestones.update');
    }

    public function delete(User $user, Milestone $milestone): bool
    {
        return $user->can('milestones.delete');
    }

    /** Class-string ability for the batch reorder endpoint. */
    public function reorder(User $user): bool
    {
        return $user->can('milestones.reorder');
    }
}
