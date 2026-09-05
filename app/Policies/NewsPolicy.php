<?php

namespace App\Policies;

use App\Models\News;
use App\Models\User;

/**
 * Publishing is its own permission: an editor writes and revises, but making
 * content public is a separate decision (plan.md Fase 2 role matrix).
 */
class NewsPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('news.view');
    }

    public function view(User $user, News $news): bool
    {
        return $user->can('news.view');
    }

    public function create(User $user): bool
    {
        return $user->can('news.create');
    }

    public function update(User $user, News $news): bool
    {
        return $user->can('news.update');
    }

    public function delete(User $user, News $news): bool
    {
        return $user->can('news.delete');
    }

    public function restore(User $user, News $news): bool
    {
        return $user->can('news.delete');
    }

    /** Permanent deletion is not recoverable, so it needs the delete right too. */
    public function forceDelete(User $user, News $news): bool
    {
        return $user->can('news.delete');
    }

    public function publish(User $user): bool
    {
        return $user->can('news.publish');
    }

    /*
     * Collection-level abilities for bulk actions. A class-string passed to
     * authorize() is dropped before the policy call, so an ability used that way
     * must not declare a model parameter.
     */

    public function updateAny(User $user): bool
    {
        return $user->can('news.update');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('news.delete');
    }
}
