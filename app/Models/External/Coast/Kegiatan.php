<?php

namespace App\Models\External\Coast;

use App\Models\Concerns\ExternalModel;

/**
 * A training/outreach activity recorded on a form (`coast_kegiatan`), with its
 * participant breakdown.
 *
 * The table is empty upstream as of this writing, so the "riwayat pelatihan"
 * section of a summary is legitimately `[]` — the endpoint is not broken, the
 * source has no rows yet. Participant *totals* do exist, on Kelompok
 * (`jumlah_orang_dilatih`), which is a different question: how many people,
 * not which sessions.
 */
class Kegiatan extends ExternalModel
{
    protected string $datasource = 'coast';

    protected $table = 'coast_kegiatan';

    protected $casts = [
        'tanggal_kegiatan' => 'date',
        'jumlah_peserta_kegiatan' => 'integer',
        'peserta_kegiatan_pria' => 'integer',
        'peserta_kegiatan_wanita' => 'integer',
        'peserta_kegiatan_remaja' => 'integer',
        'peserta_kegiatan_lansia' => 'integer',
        'peserta_kegiatan_disabilitas' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function form()
    {
        return $this->belongsTo(CoastForm::class, 'form_id', 'form_id');
    }
}
