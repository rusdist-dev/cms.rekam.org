<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\TenantModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewsCategory extends TenantModel
{
    use HasFactory, HasTranslations;

    protected $table = 'news_categories';

    protected $fillable = ['name', 'slug', 'sort_order'];

    protected $casts = [
        'name' => 'array',
        'slug' => 'array',
    ];

    protected array $translatable = ['name', 'slug'];

    public function news(): HasMany
    {
        return $this->hasMany(News::class, 'category_id');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
