<?php

namespace App\Models\External\Coast;

use App\Models\Concerns\ExternalModel;

/**
 * One economic activity recorded on a form (`coast_ekonomi`) — the only child
 * table a form routinely has several of (up to ten), one per commodity or
 * fishing/aquaculture activity.
 *
 * `modal_1`..`modal_4` and `modal_lainnya` are the components; `modal` is the
 * upstream total. Recomputing the total here would disagree with the source
 * system the moment its form logic changes, so read `modal` and leave it.
 */
class Ekonomi extends ExternalModel
{
    /**
     * Which column carries "how much was produced or sold" for each activity
     * type, and in which unit — because the answer is not the same column for
     * every activity, and `produksi` is not always kilograms.
     *
     * Mangrove-seedling sales count seedlings, tourism counts visits, and fish
     * processing puts packs in the very same `produksi` column that capture
     * and aquaculture use for kilograms. Summing `produksi` across all of them
     * would add packs to kilograms and silently drop the 203.000 seedlings.
     *
     * An activity type absent from this map falls back to `produksi` in
     * kilograms, which is what the other four types do. A new unit-based
     * activity therefore has to be added here — see docs/api-external.md.
     */
    public const OUTPUT_COLUMNS = [
        'Penjualan Bibit Mangrove' => 'jumlah_bibit_terjual',
        'Wisata' => 'jumlah_kunjungan',
        'Pengolahan Hasil Perikanan' => 'produksi',
    ];

    public const FALLBACK_OUTPUT_COLUMN = 'produksi';

    protected string $datasource = 'coast';

    protected $table = 'coast_ekonomi';

    protected $casts = [
        'jumlah_trip' => 'integer',
        'jumlah_petak_tambak' => 'integer',
        'jumlah_siklus_panen' => 'integer',
        'jumlah_produksi_bibit' => 'integer',
        'jumlah_bibit_terjual' => 'integer',
        'jumlah_kunjungan' => 'integer',
        'produksi' => 'float',
        'modal' => 'float',
        'operasional' => 'float',
        'economic_value_gross' => 'float',
        'economic_value_nett' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function form()
    {
        return $this->belongsTo(CoastForm::class, 'form_id', 'form_id');
    }
}
