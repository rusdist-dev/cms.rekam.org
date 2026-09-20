<?php

namespace App\Models\External\Coast;

use App\Models\Concerns\ExternalModel;

/**
 * A rehabilitation plot on a form (`coast_blue_carbon_rehabilitasi`) — several
 * per form, unlike the single BlueCarbon summary row.
 *
 * `geometry` is the plot outline. It is deliberately hidden: it is the largest
 * column in the table by far and useless to anything but a map, so an endpoint
 * that wants it asks for it explicitly rather than every list paying for it.
 */
class Rehabilitasi extends ExternalModel
{
    protected string $datasource = 'coast';

    protected $table = 'coast_blue_carbon_rehabilitasi';

    protected $hidden = ['geometry'];

    protected $casts = [
        'tanggal_rehabilitasi' => 'date',
        'luas_area_direhabilitasi' => 'float',
        'jumlah_bibit' => 'integer',
        'survival_rate' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function form()
    {
        return $this->belongsTo(CoastForm::class, 'form_id', 'form_id');
    }
}
