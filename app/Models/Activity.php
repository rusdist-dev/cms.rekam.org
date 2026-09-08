<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity as BaseActivity;

/**
 * The activity_log table is central and shared across every company
 * (plan.md's architecture), so `tenant_id` is how one row is scoped back to
 * the company it happened in, rather than a physically separate table.
 */
class Activity extends BaseActivity
{
    protected $fillable = [
        'log_name',
        'description',
        'subject_type',
        'subject_id',
        'causer_type',
        'causer_id',
        'properties',
        'event',
        'batch_uuid',
        'tenant_id',
    ];

    public function scopeForCurrentTenant(Builder $query): Builder
    {
        return $query->where('tenant_id', app(\App\Services\TenantManager::class)->currentId());
    }
}
