<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\LogsTenantActivity;
use App\Models\Concerns\TenantModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Publication extends TenantModel
{
    use HasFactory, HasTranslations, LogsTenantActivity;

    protected $table = 'publications';

    protected $fillable = [
        'title',
        'description',
        'category',
        'file_path',
        'file_name',
        'file_size',
        'cover_path',
        'is_featured',
        'sort_order',
    ];

    protected $casts = [
        'title' => 'array',
        'description' => 'array',
        'file_size' => 'integer',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected array $translatable = ['title', 'description'];

    public function scopeCategory(Builder $query, ?string $category): Builder
    {
        return $category ? $query->where('category', $category) : $query;
    }

    public function scopeFeatured(Builder $query, ?string $isFeatured): Builder
    {
        return $isFeatured === null || $isFeatured === ''
            ? $query
            : $query->where('is_featured', filter_var($isFeatured, FILTER_VALIDATE_BOOLEAN));
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('title->id', 'like', "%{$term}%")
                ->orWhere('file_name', 'like', "%{$term}%");
        });
    }

    protected static function activityModule(): string
    {
        return 'publications';
    }
}
