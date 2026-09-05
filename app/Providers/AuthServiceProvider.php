<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\News;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\EventPolicy;
use App\Policies\NewsPolicy;
use App\Policies\RolePolicy;
use App\Policies\TenantPolicy;
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
        User::class => UserPolicy::class,
        Role::class => RolePolicy::class,
        Tenant::class => TenantPolicy::class,
    ];

    public function boot(): void
    {
        //
    }
}
