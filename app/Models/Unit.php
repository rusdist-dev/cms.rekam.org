<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\LogsTenantActivity;
use App\Models\Concerns\TenantModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Unit extends TenantModel
{
    use HasFactory, HasTranslations, LogsTenantActivity;

    protected $table = 'units';

    protected $fillable = [
        'name',
        'description',
        'url',
        'domain',
        'logo_path',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'description' => 'array',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    protected array $translatable = ['description'];

    public function scopeActive(Builder $query, ?string $isActive): Builder
    {
        return $isActive === null || $isActive === ''
            ? $query
            : $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN));
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('domain', 'like', "%{$term}%");
        });
    }

    protected static function activityModule(): string
    {
        return 'units';
    }
}
