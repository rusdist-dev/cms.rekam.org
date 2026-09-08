<?php

namespace App\Policies;

use App\Models\Publication;
use App\Models\User;

class PublicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('publications.view');
    }

    public function view(User $user, Publication $publication): bool
    {
        return $user->can('publications.view');
    }

    public function create(User $user): bool
    {
        return $user->can('publications.create');
    }

    public function update(User $user, Publication $publication): bool
    {
        return $user->can('publications.update');
    }

    public function delete(User $user, Publication $publication): bool
    {
        return $user->can('publications.delete');
    }
}
