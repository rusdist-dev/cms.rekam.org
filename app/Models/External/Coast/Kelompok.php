<?php

namespace App\Models\External\Coast;

use App\Models\Concerns\ExternalModel;

/**
 * Community figures for one form (`coast_kelompok`): population, people trained,
 * people involved, and the fisher/processor/marketer split — each with its own
 * gender and age breakdown.
 *
 * The breakdown columns are upstream totals, not derived: `jumlah_orang_dilatih`
 * is not guaranteed to equal its pria/wanita pair, because the source form lets
 * an enumerator leave a breakdown blank while still reporting a total.
 */
class Kelompok extends ExternalModel
{
    protected string $datasource = 'coast';

    protected $table = 'coast_kelompok';

    protected $casts = [
        'populasi' => 'integer',
        'populasi_pria' => 'integer',
        'populasi_wanita' => 'integer',
        'kemiskinan_per_kabupaten' => 'float',
        'jumlah_orang_dilatih' => 'integer',
        'jumlah_orang_terlibat' => 'integer',
        'jumlah_nelayan' => 'integer',
        'jumlah_pengolah' => 'integer',
        'jumlah_pemasar' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function form()
    {
        return $this->belongsTo(CoastForm::class, 'form_id', 'form_id');
    }
}
