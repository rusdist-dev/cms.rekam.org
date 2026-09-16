<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\LogsTenantActivity;
use App\Models\Concerns\TenantModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class News extends TenantModel
{
    use HasFactory, HasTranslations, LogsTenantActivity, SoftDeletes;

    protected $table = 'news';

    protected $fillable = [
        'category_id',
        'title',
        'slug',
        'excerpt',
        'body',
        'meta_title',
        'meta_description',
        'cover_path',
        'related_programs',
        'status',
        'published_at',
        'author_name',
    ];

    protected $casts = [
        'title' => 'array',
        'slug' => 'array',
        'excerpt' => 'array',
        'body' => 'array',
        'meta_title' => 'array',
        'meta_description' => 'array',
        'related_programs' => 'array',
        'published_at' => 'datetime',
        'views' => 'integer',
    ];

    protected array $translatable = ['title', 'slug', 'excerpt', 'body', 'meta_title', 'meta_description'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(NewsCategory::class, 'category_id');
    }

    /**
     * What the public API may serve: published, and not scheduled for later.
     * A `scheduled` row becomes visible when its time passes, without a job
     * having to flip the status first.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereIn('status', ['published', 'scheduled'])
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return $status ? $query->where('status', $status) : $query;
    }

    /** Matches one programme slug inside the JSON array column. */
    public function scopeProgram(Builder $query, ?string $slug): Builder
    {
        return $slug
            ? $query->whereJsonContains('related_programs', $slug)
            : $query;
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            foreach (config('cms.locales') as $locale) {
                $q->orWhere("title->{$locale}", 'like', "%{$term}%");
            }

            $q->orWhere('author_name', 'like', "%{$term}%");
        });
    }

    /**
     * Public-facing search: a reader types words from a headline or from the
     * article itself, so the title, the excerpt and the body are all matched,
     * in either locale (the dashboard's scopeSearch is deliberately narrower —
     * it searches titles and authors, not article bodies).
     */
    public function scopeSearchContent(Builder $query, mixed $term): Builder
    {
        $term = is_scalar($term) ? trim((string) $term) : '';

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            foreach (config('cms.locales') as $locale) {
                $q->orWhere("title->{$locale}", 'like', "%{$term}%")
                    ->orWhere("excerpt->{$locale}", 'like', "%{$term}%")
                    ->orWhere("body->{$locale}", 'like', "%{$term}%");
            }
        });
    }

    /**
     * Articles published in one calendar year. Anything that isn't a plain
     * 4-digit year is ignored rather than returning an empty list, the same
     * way the other optional filters behave.
     */
    public function scopePublishedYear(Builder $query, mixed $year): Builder
    {
        $year = is_scalar($year) ? (string) $year : '';

        return ctype_digit($year) && strlen($year) === 4
            ? $query->whereYear('published_at', (int) $year)
            : $query;
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    protected static function activityModule(): string
    {
        return 'news';
    }
}
