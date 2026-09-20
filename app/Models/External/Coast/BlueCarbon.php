<?php

namespace App\Models\External\Coast;

use App\Models\Concerns\ExternalModel;

/** Blue-carbon summary for one form (`coast_blue_carbons`): rehabilitated and conserved area, seedlings, carbon stock. */
class BlueCarbon extends ExternalModel
{
    protected string $datasource = 'coast';

    protected $table = 'coast_blue_carbons';

    protected $casts = [
        'tanggal_rehabilitasi' => 'date',
        'luas_area_direhabilitasi' => 'float',
        'luas_area_dikonservasi' => 'float',
        'jumlah_bibit' => 'integer',
        'survival_rate' => 'float',
        'nilai_stok_karbon' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function form()
    {
        return $this->belongsTo(CoastForm::class, 'form_id', 'form_id');
    }

    public function kawasanKonservasi()
    {
        return $this->belongsTo(KawasanKonservasi::class, 'kawasan_konservasi_id');
    }
}
