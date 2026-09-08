<?php

namespace App\Models\Concerns;

use App\Services\TenantManager;
use App\Support\Labels;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Stamps every logged activity with the tenant it happened in, and derives a
 * human description from the same module labels the permission matrix and
 * sidebar already use, so nothing here needs its own translation strings.
 */
trait LogsTenantActivity
{
    use LogsActivity;

    /** The `permissions.modules` key this model's activity is filed under, e.g. 'news'. */
    abstract protected static function activityModule(): string;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /** Called by the package just before the activity row is saved. */
    public function tapActivity(Activity $activity, string $eventName): void
    {
        $activity->tenant_id = app(TenantManager::class)->currentId();
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        $verbs = [
            'created' => 'dibuat',
            'updated' => 'diperbarui',
            'deleted' => 'dihapus',
            'restored' => 'dipulihkan',
        ];

        return Labels::module(static::activityModule()).' '.($verbs[$eventName] ?? $eventName);
    }
}
