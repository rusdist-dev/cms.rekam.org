<?php

namespace App\Models\External\Coast;

use App\Models\Concerns\ExternalModel;

/** A marine protected area (`kawasan_konservasi`), referenced by BlueCarbon rows. */
class KawasanKonservasi extends ExternalModel
{
    protected string $datasource = 'coast';

    protected $table = 'kawasan_konservasi';

    protected $casts = [
        'luas_area_dikonservasi' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
