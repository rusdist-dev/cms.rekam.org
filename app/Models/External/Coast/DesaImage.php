<?php

namespace App\Models\External\Coast;

use App\Models\Concerns\ExternalModel;

/**
 * A village photo (`desa_images`), keyed by `kode` — the wilayah code, not a
 * form id, so one photo belongs to every form collected in that village.
 */
class DesaImage extends ExternalModel
{
    protected string $datasource = 'coast';

    protected $table = 'desa_images';

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
