<?php

namespace App\Models\External\Coast;

use App\Models\Concerns\ExternalModel;

/**
 * The map geometry for one village (`wilayah_boundaries`), keyed by the same
 * `kode` that sits on a form's `desa_kode`.
 *
 * `path` is a JSON array of polygon rings whose points are **[lat, lng]** —
 * the opposite order to GeoJSON's [lng, lat]. It is decoded here so consumers
 * never have to double-decode, and left in that order because that is what
 * Leaflet (and the existing map code) expects.
 */
class WilayahBoundary extends ExternalModel
{
    protected string $datasource = 'coast';

    protected $table = 'wilayah_boundaries';

    protected $primaryKey = 'kode';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'luas' => 'float',
        'penduduk' => 'integer',
        'path' => 'array',
        'fetched_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
