<?php

namespace App\Policies;

use App\Models\ContactMessage;
use App\Models\User;

/**
 * Nobody fills a contact message in through the CMS — it only ever arrives
 * through the (Fase 6) public form — so there is no `create` ability.
 */
class ContactMessagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('contacts.view');
    }

    public function view(User $user, ContactMessage $message): bool
    {
        return $user->can('contacts.view');
    }

    public function delete(User $user, ContactMessage $message): bool
    {
        return $user->can('contacts.delete');
    }

    /** Marking read (a side effect of `show`) and archiving share this ability. */
    public function manage(User $user, ContactMessage $message): bool
    {
        return $user->can('contacts.update');
    }
}
