<?php

namespace App\Providers;

use App\Models\ContactMessage;
use App\Models\Event;
use App\Models\Milestone;
use App\Models\News;
use App\Models\Partner;
use App\Models\Publication;
use App\Models\TeamMember;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Policies\ContactMessagePolicy;
use App\Policies\EventPolicy;
use App\Policies\MilestonePolicy;
use App\Policies\NewsPolicy;
use App\Policies\PartnerPolicy;
use App\Policies\PublicationPolicy;
use App\Policies\RolePolicy;
use App\Policies\TeamMemberPolicy;
use App\Policies\TenantPolicy;
use App\Policies\UnitPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Spatie\Permission\Models\Role;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Every endpoint is authorised by a route `permission:` middleware plus a
     * policy (context.md §4.7).
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        News::class => NewsPolicy::class,
        Event::class => EventPolicy::class,
        TeamMember::class => TeamMemberPolicy::class,
        Publication::class => PublicationPolicy::class,
        Partner::class => PartnerPolicy::class,
        ContactMessage::class => ContactMessagePolicy::class,
        Milestone::class => MilestonePolicy::class,
        Unit::class => UnitPolicy::class,
        User::class => UserPolicy::class,
        Role::class => RolePolicy::class,
        Tenant::class => TenantPolicy::class,
    ];

    public function boot(): void
    {
        //
    }
}
