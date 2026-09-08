<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\LogsTenantActivity;
use App\Models\Concerns\TenantModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TeamMember extends TenantModel
{
    use HasFactory, HasTranslations, LogsTenantActivity;

    protected $table = 'team_members';

    protected $fillable = [
        'name',
        'slug',
        'position',
        'bio',
        'photo_path',
        'group',
        'email',
        'socials',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'position' => 'array',
        'bio' => 'array',
        'socials' => 'array',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    protected array $translatable = ['position', 'bio'];

    public function scopeGroup(Builder $query, ?string $group): Builder
    {
        return $group ? $query->where('group', $group) : $query;
    }

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
                ->orWhere('position->id', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%");
        });
    }

    protected static function activityModule(): string
    {
        return 'team';
    }
}
