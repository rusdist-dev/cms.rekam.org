<?php

namespace App\Models\External\Coast;

use App\Models\Concerns\ExternalModel;
use Illuminate\Database\Eloquent\Builder;

/**
 * The region reference table (`wilayah`) — the full Indonesian province →
 * kabupaten → kecamatan → desa tree, ~91k rows, of which COAST data touches
 * 19 villages.
 *
 * Nothing should ever list this table unfiltered. Filter by `level`, by
 * `parent_kode`, or by the codes actually present on the forms.
 */
class Wilayah extends ExternalModel
{
    protected string $datasource = 'coast';

    protected $table = 'wilayah';

    protected $primaryKey = 'kode';

    protected $keyType = 'string';

    public $incrementing = false;

    public function scopeChildrenOf(Builder $query, string $parentKode): Builder
    {
        return $query->where('parent_kode', $parentKode);
    }
}
