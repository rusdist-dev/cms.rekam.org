<?php

namespace App\Services\Coast;

use App\Models\External\Coast\Wilayah;
use Illuminate\Support\Collection;

/**
 * Region names, taken from the `wilayah` reference table rather than from what
 * an enumerator typed on the form.
 *
 * The form's own name columns are free text and disagree with themselves: the
 * same `desa_kode` appears as both "Teluk Awur" and "Telukawur", and one form
 * files Purworejo under kecamatan "Purworejo" instead of "Bonang". The codes,
 * by contrast, are consistent — every verified form's kecamatan/kabupaten/
 * provinsi code already matches its desa code's prefix, and all nineteen
 * villages exist in `wilayah`. So the code is the identity and this table is
 * the name.
 *
 * Codes are hierarchical by prefix (33 → 33.21 → 33.21.12 → 33.21.12.2011),
 * which is why the ancestry is derived by splitting rather than by walking
 * `parent_kode` one query per level.
 */
class CoastWilayahResolver
{
    /**
     * @param  iterable<string>  $desaKodes
     * @return Collection<string, array<string, ?string>> keyed by desa code
     */
    public function forDesaCodes(iterable $desaKodes): Collection
    {
        $codes = collect($desaKodes)->filter()->unique()->values();

        if ($codes->isEmpty()) {
            return collect();
        }

        $names = $this->names($codes);

        return $codes->mapWithKeys(fn (string $kode) => [
            $kode => $this->compose($kode, $names),
        ]);
    }

    public function forDesaCode(string $desaKode): array
    {
        return $this->forDesaCodes([$desaKode])->get($desaKode)
            ?? $this->compose($desaKode, collect());
    }

    /**
     * Fills the gaps this table cannot answer with whatever the form recorded.
     * A name from a misspelt form still beats a blank label on a map, and today
     * this never fires — it is here for a village added upstream before the
     * region reference catches up.
     *
     * @param  array<string, ?string>  $fallback  the form's own name columns
     * @param  array<string, ?string>  $resolved  from forDesaCode(s)
     */
    public function withFallback(array $resolved, array $fallback): array
    {
        foreach (['desa', 'kecamatan', 'kabupaten_kota', 'provinsi'] as $field) {
            $resolved[$field] ??= $fallback[$field] ?? null;
        }

        return $resolved;
    }

    /** One query for every level of every requested village. */
    private function names(Collection $desaKodes): Collection
    {
        $all = $desaKodes
            ->flatMap(fn (string $kode) => array_filter(array_values($this->ancestry($kode))))
            ->unique()
            ->values();

        return Wilayah::query()
            ->whereIn('kode', $all)
            ->pluck('nama', 'kode');
    }

    /** @param  Collection<string, string>  $names */
    private function compose(string $desaKode, Collection $names): array
    {
        $ancestry = $this->ancestry($desaKode);

        return [
            'desa_kode' => $ancestry['desa_kode'],
            'desa' => $names->get($ancestry['desa_kode']),
            'kecamatan_kode' => $ancestry['kecamatan_kode'],
            'kecamatan' => $ancestry['kecamatan_kode'] ? $names->get($ancestry['kecamatan_kode']) : null,
            'kabupaten_kode' => $ancestry['kabupaten_kode'],
            'kabupaten_kota' => $ancestry['kabupaten_kode'] ? $names->get($ancestry['kabupaten_kode']) : null,
            'provinsi_kode' => $ancestry['provinsi_kode'],
            'provinsi' => $ancestry['provinsi_kode'] ? $names->get($ancestry['provinsi_kode']) : null,
        ];
    }

    /** @return array{provinsi_kode: ?string, kabupaten_kode: ?string, kecamatan_kode: ?string, desa_kode: string} */
    private function ancestry(string $desaKode): array
    {
        $parts = explode('.', $desaKode);

        $prefix = fn (int $length) => count($parts) >= $length
            ? implode('.', array_slice($parts, 0, $length))
            : null;

        return [
            'provinsi_kode' => $prefix(1),
            'kabupaten_kode' => $prefix(2),
            'kecamatan_kode' => $prefix(3),
            'desa_kode' => $desaKode,
        ];
    }
}
