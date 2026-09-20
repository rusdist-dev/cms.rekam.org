<?php

namespace App\Models\External\Coast;

use App\Models\Concerns\ExternalModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One COAST data-collection form (`identitas_coast`) — the parent record every
 * other table in that database hangs off.
 *
 * Note the join key: children are related by the string `form_id`, not by `id`.
 * Relating them the Laravel-default way would silently match the wrong rows,
 * because both columns exist and both are integers-shaped enough to look right.
 */
class CoastForm extends ExternalModel
{
    protected string $datasource = 'coast';

    protected $table = 'identitas_coast';

    /**
     * Only a verified form may leave through the API — the same rule
     * `published` enforces for CMS content (context.md §4.10). An enumerator's
     * draft is working material, not something a public site should surface.
     */
    public const STATUS_VERIFIED = 'verified';

    protected $casts = [
        'tanggal_pendataan' => 'datetime',
        'verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Soft-deleted rows are excluded globally rather than through the
     * SoftDeletes trait: the trait exists to *perform* soft deletes, and its
     * write machinery both conflicts with this model's read-only guard and is
     * dead weight on a database we only ever read. All that is wanted is the
     * filter.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('not_deleted', fn (Builder $query) => $query->whereNull('deleted_at'));
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_VERIFIED);
    }

    /** `?provinsi=`, `?kabupaten=`, ... — by name or by the wilayah code. */
    public function scopeInRegion(Builder $query, string $column, ?string $value): Builder
    {
        if (blank($value)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($column, $value) {
            $q->where($column, $value)->orWhere($column.'_kode', $value);
        });
    }

    public function scopeCollectedYear(Builder $query, ?string $year): Builder
    {
        if (blank($year) || ! ctype_digit($year)) {
            return $query;
        }

        return $query->whereYear('tanggal_pendataan', $year);
    }

    /*
     * One row per form in the data as it stands, but `form_id` carries only a
     * plain index on the child tables — nothing in the schema stops a second
     * one appearing. hasOne degrades to "the first" if that ever happens,
     * which is the harmless failure; hasMany here would change the response
     * shape for every consumer.
     */
    public function ecosystem(): HasOne
    {
        return $this->hasOne(Ecosystem::class, 'form_id', 'form_id');
    }

    public function blueCarbon(): HasOne
    {
        return $this->hasOne(BlueCarbon::class, 'form_id', 'form_id');
    }

    public function kelompok(): HasOne
    {
        return $this->hasOne(Kelompok::class, 'form_id', 'form_id');
    }

    public function ekonomi(): HasMany
    {
        return $this->hasMany(Ekonomi::class, 'form_id', 'form_id');
    }

    public function rehabilitasi(): HasMany
    {
        return $this->hasMany(Rehabilitasi::class, 'form_id', 'form_id');
    }

    /** Photos are keyed by village code, so they are shared by every form in that village. */
    public function desaImages(): HasMany
    {
        return $this->hasMany(DesaImage::class, 'kode', 'desa_kode');
    }

    public function getRouteKeyName(): string
    {
        return 'form_id';
    }
}
