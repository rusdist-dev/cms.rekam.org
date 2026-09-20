<?php

namespace App\Models\External\Coast;

use App\Models\Concerns\ExternalModel;

/**
 * Ecosystem survey attached to one form (`coast_ecosystem`).
 *
 * `ekosistem` is a single column holding either one type or a comma-joined
 * list ("Mangrove,Lamun dan Terumbu Karang") — it is the upstream form's own
 * free-text field, not a normalised relation, so anything grouping by type has
 * to split it rather than GROUP BY it.
 */
class Ecosystem extends ExternalModel
{
    protected string $datasource = 'coast';

    protected $table = 'coast_ecosystem';

    protected $casts = [
        'luasan_ekosistem' => 'float',
        'nilai_ekonomi' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function form()
    {
        return $this->belongsTo(CoastForm::class, 'form_id', 'form_id');
    }
}
