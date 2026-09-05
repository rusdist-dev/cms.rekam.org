<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\TenantModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends TenantModel
{
    use HasFactory, HasTranslations, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'location',
        'fee_note',
        'meta_title',
        'meta_description',
        'category',
        'start_at',
        'end_at',
        'is_all_day',
        'fee',
        'quota',
        'registration_url',
        'cover_path',
        'status',
    ];

    protected $casts = [
        'title' => 'array',
        'slug' => 'array',
        'description' => 'array',
        'location' => 'array',
        'fee_note' => 'array',
        'meta_title' => 'array',
        'meta_description' => 'array',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'is_all_day' => 'boolean',
        'fee' => 'decimal:2',
        'quota' => 'integer',
    ];

    protected array $translatable = ['title', 'slug', 'description', 'location', 'fee_note', 'meta_title', 'meta_description'];

    public function rundowns(): HasMany
    {
        return $this->hasMany(EventRundown::class)->orderBy('sort_order');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    /** Events that have not finished yet — a single-day event ends when it starts. */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('end_at', '>=', now())
                ->orWhere(fn (Builder $inner) => $inner->whereNull('end_at')->where('start_at', '>=', now()));
        });
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            foreach (config('cms.locales') as $locale) {
                $q->orWhere("title->{$locale}", 'like', "%{$term}%")
                    ->orWhere("location->{$locale}", 'like', "%{$term}%");
            }
        });
    }

    /** A null or zero fee is free — the compro shows wording, not "Rp0". */
    public function isFree(): bool
    {
        return $this->fee === null || (float) $this->fee === 0.0;
    }
}
