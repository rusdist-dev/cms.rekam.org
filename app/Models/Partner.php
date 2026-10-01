<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\LogsTenantActivity;
use App\Models\Concerns\TenantModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Partner extends TenantModel
{
    use HasFactory, HasTranslations, LogsTenantActivity;

    protected $table = 'partners';

    protected $fillable = [
        'name',
        'title',
        'category',
        'logo_path',
        'url',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'title' => 'array',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    protected array $translatable = ['title'];

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
                ->orWhere('url', 'like', "%{$term}%");
        });
    }

    public function scopeCategory(Builder $query, ?string $category): Builder
    {
        return $category ? $query->where('category', $category) : $query;
    }

    protected static function activityModule(): string
    {
        return 'partners';
    }
}
