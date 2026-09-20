<?php

namespace App\Http\Resources\Ext\Coast;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One marine protected area from COAST's `kawasan_konservasi`.
 *
 * `created_at`/`updated_at` are deliberately not exposed: they record when the
 * upstream system last touched its own row, which says nothing about the area
 * and would read as if it did.
 */
class KawasanKonservasiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama_kawasan' => $this->nama_kawasan,
            // The national MPA register's identifier (T244, T946, ...), which
            // is how this area is referred to outside COAST.
            'id_mpa' => $this->id_mpa,
            'luas_area_dikonservasi' => $this->luas_area_dikonservasi,
            'pelaksana_konservasi' => $this->pelaksana_konservasi,
        ];
    }
}
