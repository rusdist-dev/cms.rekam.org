<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\LogsTenantActivity;
use App\Models\Concerns\TenantModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Milestone extends TenantModel
{
    use HasFactory, HasTranslations, LogsTenantActivity;

    protected $table = 'milestones';

    protected $fillable = [
        'title',
        'body',
        'cover_path',
        'year',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'title' => 'array',
        'body' => 'array',
        'year' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    protected array $translatable = ['title', 'body'];

    public function scopeYear(Builder $query, ?string $year): Builder
    {
        return $year !== null && $year !== '' ? $query->where('year', $year) : $query;
    }

    public function scopeActive(Builder $query, ?string $isActive): Builder
    {
        return $isActive === null || $isActive === ''
            ? $query
            : $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN));
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $term ? $query->where('title->id', 'like', "%{$term}%") : $query;
    }

    protected static function activityModule(): string
    {
        return 'milestones';
    }
}
