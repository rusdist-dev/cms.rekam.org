<?php

namespace App\Services\Coast;

use App\Models\External\Coast\CoastForm;
use App\Models\External\Coast\DesaImage;
use App\Models\External\Coast\Ekonomi;
use App\Models\External\Coast\Kegiatan;
use App\Models\External\Coast\Rehabilitasi;
use App\Models\External\Coast\WilayahBoundary;
use App\Services\DatasourceRegistry;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Everything the map panel shows when a village is clicked: two years of
 * statistics, photos, and the rehabilitation and training histories.
 *
 * The twenty-four statistics come from four queries, not twenty-four. Each
 * source table is visited once and every metric — both years of it — is a
 * conditional SUM in that one query. A metric-at-a-time loop over a database
 * owned by another system is the one shape this must not take.
 *
 * The year window is a date range rather than `YEAR(tanggal_pendataan) = ?`,
 * so the comparison can use an index on that column instead of forcing a scan.
 */
class CoastSummaryService
{
    /** Ecosystem types broken out individually, keyed by the metric suffix. */
    private const ECOSYSTEM_TYPES = [
        'mangrove' => 'Mangrove',
        'lamun' => 'Lamun',
        'terumbu_karang' => 'Terumbu Karang',
    ];

    /**
     * Shared by the "dilatih" and "terlibat" breakdowns: column prefix => label.
     *
     * These categories overlap — a woman under thirty is counted in both
     * `wanita` and `remaja` — and upstream lets an enumerator report a total
     * without filling any of them. So they are children of their total for
     * display, but they do not add up to it; see `children_sum_to_total`.
     */
    private const DEMOGRAPHICS = [
        'pria' => 'Pria',
        'wanita' => 'Wanita',
        'remaja' => 'Remaja',
        'lansia' => 'Lansia',
        'disabilitas' => 'Disabilitas',
    ];

    public function __construct(
        private readonly DatasourceRegistry $datasources,
        private readonly CoastWilayahResolver $wilayah,
    ) {}

    /**
     * @throws ModelNotFoundException when the village has no verified form —
     *                                which the API turns into a 404 rather than
     *                                an all-zero summary that looks like data.
     */
    public function forDesa(string $desaKode): array
    {
        // Newest first — the latest form supplies the collection date and the
        // fallback names. The names themselves come from `wilayah`, because the
        // form's own name columns disagree with each other for the same
        // desa_kode (CoastWilayahResolver).
        $forms = CoastForm::query()
            ->verified()
            ->where('desa_kode', $desaKode)
            ->orderByDesc('tanggal_pendataan')
            ->orderByDesc('id')
            ->get([
                'id', 'form_id', 'desa_kode', 'desa', 'kecamatan',
                'kabupaten_kota', 'provinsi', 'tanggal_pendataan',
            ]);

        if ($forms->isEmpty()) {
            throw (new ModelNotFoundException)->setModel(CoastForm::class, [$desaKode]);
        }

        $latest = $forms->first();
        $formIds = $forms->pluck('form_id')->all();

        $recent = (int) now()->year;
        $previous = $recent - 1;

        return [
            'wilayah' => $this->wilayah->withFallback(
                $this->wilayah->forDesaCode($desaKode),
                [
                    'desa' => $latest->desa,
                    'kecamatan' => $latest->kecamatan,
                    'kabupaten_kota' => $latest->kabupaten_kota,
                    'provinsi' => $latest->provinsi,
                ],
            ),
            'peta' => $this->boundary($desaKode),
            'pendataan' => [
                'jumlah_form' => $forms->count(),
                'terakhir' => $latest->tanggal_pendataan?->toDateString(),
            ],
            'statistik' => [
                'tahun_baru' => $recent,
                'tahun_lama' => $previous,
                'metrik' => $this->statistics($desaKode, $recent, $previous),
            ],
            'gambar' => $this->images($desaKode),
            'rehabilitasi' => $this->rehabilitations($formIds),
            'pelatihan' => $this->trainings($formIds),
        ];
    }

    /**
     * The same two-year statistics with no village filter: every verified form
     * in the COAST database, for a national headline panel.
     *
     * A deliberately shorter list than a village summary, and a flat one. The
     * two figures a summary derives — total ecosystem area and total economic
     * value — are omitted rather than nested here: both are sums whose parts
     * mean different things at this scale (a combined-ecosystem row that can be
     * attributed nowhere; a valuation three orders of magnitude larger than the
     * income beside it), and a single headline number hides exactly that.
     */
    public function overall(): array
    {
        $recent = (int) now()->year;
        $previous = $recent - 1;

        $values = $this->aggregate(null, $recent, $previous);

        return [
            'pendataan' => $this->coverage(),
            'statistik' => [
                'tahun_baru' => $recent,
                'tahun_lama' => $previous,
                'metrik' => array_map(
                    fn (array $definition) => $this->metricRow($definition, $values, []),
                    $this->overallDefinitions(),
                ),
            ],
        ];
    }

    /**
     * How much data stands behind the totals.
     *
     * All-time, not the two-year window, and the same reading as a village
     * summary's `pendataan`: how many surveys exist and when the most recent
     * one was collected.
     */
    private function coverage(): array
    {
        $row = CoastForm::query()
            ->verified()
            ->selectRaw('count(*) as jumlah_form')
            ->selectRaw('count(distinct desa_kode) as jumlah_desa')
            ->selectRaw('max(tanggal_pendataan) as terakhir')
            ->first();

        return [
            'jumlah_form' => (int) $row->jumlah_form,
            'jumlah_desa' => (int) $row->jumlah_desa,
            'terakhir' => $row->terakhir ? Carbon::parse($row->terakhir)->toDateString() : null,
        ];
    }

    /**
     * The twelve figures the national panel shows, flat.
     *
     * @return array<int, array>
     */
    private function overallDefinitions(): array
    {
        return [
            ['key' => 'luas_ekosistem_mangrove', 'label' => 'Luas Ekosistem Mangrove', 'unit' => 'ha', 'decimals' => 2],
            ['key' => 'luas_ekosistem_lamun', 'label' => 'Luas Ekosistem Lamun', 'unit' => 'ha', 'decimals' => 2],
            ['key' => 'luas_ekosistem_terumbu_karang', 'label' => 'Luas Ekosistem Terumbu Karang', 'unit' => 'ha', 'decimals' => 2],
            ['key' => 'nilai_valuasi', 'label' => 'Nilai Valuasi', 'unit' => 'Rp', 'decimals' => 2],
            ['key' => 'nilai_pendapatan', 'label' => 'Nilai Pendapatan', 'unit' => 'Rp', 'decimals' => 2],
            ['key' => 'luas_area_konservasi', 'label' => 'Luas Area Konservasi', 'unit' => 'ha', 'decimals' => 2],
            ['key' => 'luas_area_direhabilitasi', 'label' => 'Luas Area Direhabilitasi', 'unit' => 'ha', 'decimals' => 2],
            ['key' => 'dampak_ekonomi_produksi', 'label' => 'Dampak Ekonomi (Produksi)', 'unit' => 'kg', 'decimals' => 2],
            ['key' => 'dampak_ekonomi_unit_terjual', 'label' => 'Dampak Ekonomi (Unit Terjual)', 'unit' => 'unit', 'decimals' => 0],
            ['key' => 'orang_dilatih_total', 'label' => 'Total Orang Dilatih', 'unit' => 'orang', 'decimals' => 0],
            ['key' => 'orang_terlibat_total', 'label' => 'Total Orang Terlibat', 'unit' => 'orang', 'decimals' => 0],
            ['key' => 'nilai_stok_karbon', 'label' => 'Nilai Stok Karbon', 'unit' => 'Mg C', 'decimals' => 2],
        ];
    }

    /** The polygon the map draws, or null for a village with no boundary row. */
    private function boundary(string $desaKode): ?array
    {
        $boundary = WilayahBoundary::query()->find($desaKode);

        if ($boundary === null) {
            return null;
        }

        return [
            'lat' => $boundary->lat,
            'lng' => $boundary->lng,
            'luas' => $boundary->luas,
            'penduduk' => $boundary->penduduk,
            // Already decoded by the model's cast. Points are [lat, lng].
            'path' => $boundary->path,
        ];
    }

    /**
     * The two `dampak_ekonomi_*` rows carry an extra `children` key: the same
     * figure broken down by activity type.
     *
     * @return array<int, array{key: string, label: string, unit: ?string, decimals: int, baru: float, lama: float, children?: array}>
     */
    private function statistics(string $desaKode, int $recent, int $previous): array
    {
        return array_map(
            fn (array $definition) => $this->metricRow(
                $definition,
                $this->aggregate($desaKode, $recent, $previous),
                $this->outputByActivity($desaKode, $recent, $previous),
            ),
            $this->definitions(),
        );
    }

    /**
     * Every metric's two-year figures, for one village or for all of them.
     *
     * @return array<string, array{baru: float, lama: float}>
     */
    private function aggregate(?string $desaKode, int $recent, int $previous): array
    {
        $values = array_merge(
            $this->totals($this->scoped('coast_ecosystem', 'e', $desaKode), $this->ecosystemMetrics(), $recent, $previous),
            $this->totals($this->scoped('coast_blue_carbons', 'b', $desaKode), $this->blueCarbonMetrics(), $recent, $previous),
            $this->totals($this->scoped('coast_ekonomi', 'e', $desaKode), $this->economyMetrics(), $recent, $previous),
            $this->totals($this->scoped('coast_kelompok', 'k', $desaKode), $this->communityMetrics(), $recent, $previous),
        );

        // Derived, not stored anywhere upstream. Valuation (the worth
        // attributed to the ecosystem) and income (what people actually earned)
        // live in different tables and differ by three orders of magnitude, so
        // the total is only meaningful stated alongside both — which is why all
        // three are returned, not just the sum.
        $values['nilai_ekonomi_total'] = [
            'baru' => ($values['nilai_valuasi']['baru'] ?? 0) + ($values['nilai_pendapatan']['baru'] ?? 0),
            'lama' => ($values['nilai_valuasi']['lama'] ?? 0) + ($values['nilai_pendapatan']['lama'] ?? 0),
        ];

        return $values;
    }

    /**
     * One metric and, where it has them, its children.
     *
     * A definition either declares fixed children (the ecosystem types, the
     * two halves of economic value, the SDM categories) or names a metric whose
     * children are discovered from the data — the activity/commodity tree. The
     * two are never mixed on one metric.
     *
     * @param  array<string, array{baru: float, lama: float}>  $values
     * @param  array<string, array>  $breakdown
     */
    private function metricRow(array $definition, array $values, array $breakdown): array
    {
        $decimals = $definition['decimals'];

        $row = [
            'key' => $definition['key'],
            'label' => $definition['label'],
            'unit' => $definition['unit'],
            'decimals' => $decimals,
            'baru' => round((float) ($values[$definition['key']]['baru'] ?? 0), $decimals),
            'lama' => round((float) ($values[$definition['key']]['lama'] ?? 0), $decimals),
        ];

        $children = isset($breakdown[$definition['key']])
            ? $this->decorate($breakdown[$definition['key']], $definition['unit'], $decimals)
            : array_map(
                fn (array $child) => $this->metricRow($child, $values, $breakdown),
                $definition['children'] ?? [],
            );

        if ($children === [] && ! array_key_exists($definition['key'], $breakdown)) {
            return $row;
        }

        // Whether a client may treat the children as a complete decomposition —
        // safe to chart as a pie, to sum, to label "lainnya" with the remainder.
        // False where the parent is the honest figure and the children are only
        // the part that could be attributed (a combined ecosystem row, an
        // unfilled SDM breakdown).
        $row['children_sum_to_total'] = $definition['children_sum_to_total'];
        $row['children'] = $children;

        return $row;
    }

    /**
     * Gives a discovered subtree the unit and precision of the metric it hangs
     * under, recursing into the commodity level.
     */
    private function decorate(array $nodes, ?string $unit, int $decimals): array
    {
        return array_map(function (array $node) use ($unit, $decimals) {
            $row = [
                'key' => $node['key'],
                'label' => $node['label'],
                'unit' => $unit,
                'decimals' => $decimals,
                'baru' => round($node['baru'], $decimals),
                'lama' => round($node['lama'], $decimals),
            ];

            if (! empty($node['children'])) {
                // A commodity belongs to exactly one activity, so this level is
                // always a complete decomposition of the one above it.
                $row['children_sum_to_total'] = true;
                $row['children'] = $this->decorate($node['children'], $unit, $decimals);
            }

            return $row;
        }, $nodes);
    }

    /**
     * Production and units sold, as an activity → commodity tree — the
     * `children` of the two `dampak_ekonomi_*` metrics.
     *
     * Every level is a complete decomposition of the one above it. An activity
     * lands under exactly one metric, because the unit it reports in is what
     * decides which of the two it belongs to (Ekonomi::OUTPUT_COLUMNS); and a
     * row carries exactly one commodity. Unlike the SDM breakdowns, these do
     * sum to their parent.
     *
     * Still one query: grouping by both levels at once and assembling the tree
     * here, rather than a query per activity.
     *
     * @return array<string, array<int, array>>
     */
    private function outputByActivity(?string $desaKode, int $recent, int $previous): array
    {
        [$amountSql, $amountBindings] = $this->outputAmount();

        $query = $this->scoped('coast_ekonomi', 'e', $desaKode)
            ->where('i.tanggal_pendataan', '>=', $this->yearStart($previous))
            ->where('i.tanggal_pendataan', '<', $this->yearStart($recent + 1))
            ->groupBy('e.jenis_kegiatan', 'e.komoditas')
            ->select('e.jenis_kegiatan', 'e.komoditas');

        foreach (['baru' => $recent, 'lama' => $previous] as $slot => $year) {
            $query->selectRaw(
                "sum(case when i.tanggal_pendataan >= ? and i.tanggal_pendataan < ? then ({$amountSql}) else 0 end) as {$slot}",
                array_merge([$this->yearStart($year), $this->yearStart($year + 1)], $amountBindings),
            );
        }

        $tree = [
            'dampak_ekonomi_produksi' => [],
            'dampak_ekonomi_unit_terjual' => [],
        ];

        foreach ($query->get() as $row) {
            $activity = (string) $row->jenis_kegiatan;

            // An activity listed in OUTPUT_COLUMNS reports in units; everything
            // else reports kilograms through the fallback column.
            $parent = array_key_exists($activity, Ekonomi::OUTPUT_COLUMNS)
                ? 'dampak_ekonomi_unit_terjual'
                : 'dampak_ekonomi_produksi';

            $activityKey = $this->nodeKey($activity);

            $tree[$parent][$activityKey] ??= $this->node($activity);
            $tree[$parent][$activityKey]['baru'] += (float) $row->baru;
            $tree[$parent][$activityKey]['lama'] += (float) $row->lama;

            $commodity = (string) $row->komoditas;
            $commodityKey = $this->nodeKey($commodity);

            $tree[$parent][$activityKey]['children'][$commodityKey] ??= $this->node($commodity);
            $tree[$parent][$activityKey]['children'][$commodityKey]['baru'] += (float) $row->baru;
            $tree[$parent][$activityKey]['children'][$commodityKey]['lama'] += (float) $row->lama;
        }

        return array_map(fn (array $level) => $this->sortNodes($level), $tree);
    }

    /** A fresh tree node for a label that may be blank upstream. */
    private function node(string $label): array
    {
        return [
            'key' => $this->nodeKey($label),
            'label' => $label !== '' ? $label : 'Lainnya',
            'baru' => 0.0,
            'lama' => 0.0,
            'children' => [],
        ];
    }

    private function nodeKey(string $label): string
    {
        return Str::slug($label) ?: 'lainnya';
    }

    /**
     * Biggest current-year contributor first, at every level — an accordion is
     * read from the top, and the name is only a tie-break. Also drops the
     * string keys used for grouping, so the JSON is an array and not an object.
     */
    private function sortNodes(array $nodes): array
    {
        $nodes = array_values(array_map(function (array $node) {
            $node['children'] = $node['children'] === [] ? [] : $this->sortNodes($node['children']);

            return $node;
        }, $nodes));

        usort($nodes, fn (array $a, array $b) => [$b['baru'], $a['label']] <=> [$a['baru'], $b['label']]);

        return $nodes;
    }

    /**
     * How much a single `coast_ekonomi` row produced or sold, in whatever unit
     * that row's activity reports in.
     *
     * @return array{0: string, 1: array}
     */
    private function outputAmount(): array
    {
        $sql = 'case';
        $bindings = [];

        foreach (Ekonomi::OUTPUT_COLUMNS as $activity => $column) {
            $sql .= " when e.jenis_kegiatan = ? then e.{$column}";
            $bindings[] = $activity;
        }

        return [$sql.' else e.'.Ekonomi::FALLBACK_OUTPUT_COLUMN.' end', $bindings];
    }

    /**
     * Area and valuation, from `coast_ecosystem`.
     *
     * The per-type figures match the ecosystem name exactly. A row naming
     * several types at once ("Mangrove,Lamun dan Terumbu Karang") carries a
     * single combined area that cannot honestly be split between them, so it
     * counts towards the total and towards none of the three. Matching with
     * LIKE instead would count that one area three times over.
     *
     * @return array<string, array{0: string, 1: array}>
     */
    private function ecosystemMetrics(): array
    {
        $metrics = [
            'luas_ekosistem_total' => ['e.luasan_ekosistem', []],
            'nilai_valuasi' => ['e.nilai_ekonomi', []],
        ];

        foreach (self::ECOSYSTEM_TYPES as $suffix => $name) {
            $metrics["luas_ekosistem_{$suffix}"] = [
                'case when trim(e.ekosistem) = ? then e.luasan_ekosistem else 0 end',
                [$name],
            ];
        }

        return $metrics;
    }

    /** @return array<string, array{0: string, 1: array}> */
    private function blueCarbonMetrics(): array
    {
        return [
            'luas_area_konservasi' => ['b.luas_area_dikonservasi', []],
            'luas_area_direhabilitasi' => ['b.luas_area_direhabilitasi', []],
            'nilai_stok_karbon' => ['b.nilai_stok_karbon', []],
        ];
    }

    /**
     * Income and physical output, from `coast_ekonomi`.
     *
     * The two output metrics are split by activity type rather than by column,
     * because the same `produksi` column means kilograms for capture fisheries
     * and packs for processing — see Ekonomi::OUTPUT_COLUMNS.
     *
     * @return array<string, array{0: string, 1: array}>
     */
    private function economyMetrics(): array
    {
        $unitSql = '';
        $unitBindings = [];

        foreach (Ekonomi::OUTPUT_COLUMNS as $activity => $column) {
            $unitSql .= " when e.jenis_kegiatan = ? then e.{$column}";
            $unitBindings[] = $activity;
        }

        $unitActivities = array_keys(Ekonomi::OUTPUT_COLUMNS);

        $placeholders = implode(', ', array_fill(0, count($unitActivities), '?'));
        $fallback = Ekonomi::FALLBACK_OUTPUT_COLUMN;

        return [
            'nilai_pendapatan' => ['e.economic_value_nett', []],
            'dampak_ekonomi_unit_terjual' => ["case{$unitSql} else 0 end", $unitBindings],
            'dampak_ekonomi_produksi' => [
                "case when e.jenis_kegiatan in ({$placeholders}) then 0 else e.{$fallback} end",
                $unitActivities,
            ],
        ];
    }

    /**
     * People trained and involved, from `coast_kelompok`.
     *
     * The totals are read, never recomputed from the breakdowns: upstream lets
     * an enumerator report a total without filling the split, and the age and
     * gender categories overlap anyway, so pria + wanita + remaja + lansia +
     * disabilitas is not expected to equal the total.
     *
     * @return array<string, array{0: string, 1: array}>
     */
    private function communityMetrics(): array
    {
        $metrics = [];

        foreach (['dilatih', 'terlibat'] as $kind) {
            $metrics["orang_{$kind}_total"] = ["k.jumlah_orang_{$kind}", []];

            foreach (array_keys(self::DEMOGRAPHICS) as $group) {
                $metrics["orang_{$kind}_{$group}"] = ["k.{$group}_orang_{$kind}", []];
            }
        }

        return $metrics;
    }

    /**
     * A query over one child table, restricted to verified, non-deleted forms —
     * in one village, or across all of them when $desaKode is null. The year
     * windowing is applied per metric in totals().
     */
    private function scoped(string $table, string $alias, ?string $desaKode): Builder
    {
        return $this->datasources->connection('coast')
            ->table("{$table} as {$alias}")
            ->join('identitas_coast as i', 'i.form_id', '=', "{$alias}.form_id")
            ->where('i.status', CoastForm::STATUS_VERIFIED)
            ->whereNull('i.deleted_at')
            ->when($desaKode !== null, fn (Builder $q) => $q->where('i.desa_kode', $desaKode));
    }

    /**
     * Both years of every metric, in a single row.
     *
     * @param  array<string, array{0: string, 1: array}>  $metrics  key => [sql expression, bindings]
     * @return array<string, array{baru: float, lama: float}>
     */
    private function totals(Builder $query, array $metrics, int $recent, int $previous): array
    {
        $slots = ['baru' => $recent, 'lama' => $previous];

        // Narrow the scan to the two years before the per-metric CASEs split
        // them apart, so the join reads only rows that can contribute.
        $query->where('i.tanggal_pendataan', '>=', $this->yearStart($previous))
            ->where('i.tanggal_pendataan', '<', $this->yearStart($recent + 1));

        foreach ($metrics as $key => [$sql, $bindings]) {
            foreach ($slots as $slot => $year) {
                $query->selectRaw(
                    "sum(case when i.tanggal_pendataan >= ? and i.tanggal_pendataan < ? then ({$sql}) else 0 end) as {$key}__{$slot}",
                    array_merge([$this->yearStart($year), $this->yearStart($year + 1)], $bindings),
                );
            }
        }

        $row = $query->first();
        $values = [];

        foreach (array_keys($metrics) as $key) {
            foreach (array_keys($slots) as $slot) {
                $values[$key][$slot] = (float) ($row?->{"{$key}__{$slot}"} ?? 0);
            }
        }

        return $values;
    }

    private function yearStart(int $year): string
    {
        return sprintf('%04d-01-01 00:00:00', $year);
    }

    private function images(string $desaKode): array
    {
        return DesaImage::query()
            ->where('kode', $desaKode)
            ->orderBy('id')
            ->get(['url', 'keterangan'])
            ->map(fn (DesaImage $image) => [
                'url' => $image->url,
                'keterangan' => $image->keterangan,
            ])
            ->all();
    }

    /**
     * Full history, not the two-year window — "riwayat" is the whole record,
     * and the statistics above already answer the recent-years question.
     */
    private function rehabilitations(array $formIds): array
    {
        return Rehabilitasi::query()
            ->whereIn('form_id', $formIds)
            ->whereNotNull('tanggal_rehabilitasi')
            ->orderByDesc('tanggal_rehabilitasi')
            ->limit(100)
            ->get()
            ->map(fn (Rehabilitasi $r) => [
                'tanggal' => $r->tanggal_rehabilitasi?->toDateString(),
                'ekosistem' => $r->ekosistem,
                'status_lahan' => $r->status_lahan,
                'luas_area_direhabilitasi' => $r->luas_area_direhabilitasi,
                'pelaksana' => $r->pelaksana_rehabilitasi,
                'kolaborator' => $r->kolaborator,
                'jumlah_bibit' => $r->jumlah_bibit,
                'survival_rate' => $r->survival_rate,
            ])
            ->all();
    }

    private function trainings(array $formIds): array
    {
        return Kegiatan::query()
            ->whereIn('form_id', $formIds)
            ->whereNotNull('tanggal_kegiatan')
            ->orderByDesc('tanggal_kegiatan')
            ->limit(100)
            ->get()
            ->map(fn (Kegiatan $k) => [
                'tanggal' => $k->tanggal_kegiatan?->toDateString(),
                'nama' => $k->nama_kegiatan,
                'peserta' => $k->jumlah_peserta_kegiatan,
                'peserta_pria' => $k->peserta_kegiatan_pria,
                'peserta_wanita' => $k->peserta_kegiatan_wanita,
                'peserta_remaja' => $k->peserta_kegiatan_remaja,
                'peserta_lansia' => $k->peserta_kegiatan_lansia,
                'peserta_disabilitas' => $k->peserta_kegiatan_disabilitas,
            ])
            ->all();
    }

    /**
     * The shape of the statistics block: nine top-level metrics, four of which
     * decompose into children.
     *
     * `children_sum_to_total` is the field that keeps a client honest. Where it
     * is false the parent is the reported figure and the children are only the
     * part that could be attributed — charting the children as a whole, or
     * summing them in place of the parent, would understate it.
     *
     * @return array<int, array>
     */
    private function definitions(): array
    {
        return [
            [
                'key' => 'luas_ekosistem_total', 'label' => 'Total Luas Ekosistem', 'unit' => 'ha', 'decimals' => 2,
                // A row naming several ecosystems at once carries one combined
                // area that belongs to no single type, so it reaches the total
                // and none of the three children.
                'children_sum_to_total' => false,
                'children' => [
                    ['key' => 'luas_ekosistem_mangrove', 'label' => 'Mangrove', 'unit' => 'ha', 'decimals' => 2],
                    ['key' => 'luas_ekosistem_lamun', 'label' => 'Lamun', 'unit' => 'ha', 'decimals' => 2],
                    ['key' => 'luas_ekosistem_terumbu_karang', 'label' => 'Terumbu Karang', 'unit' => 'ha', 'decimals' => 2],
                ],
            ],
            [
                'key' => 'nilai_ekonomi_total', 'label' => 'Total Nilai Ekonomi', 'unit' => 'Rp', 'decimals' => 2,
                // This total is defined as the sum of its two children.
                'children_sum_to_total' => true,
                'children' => [
                    ['key' => 'nilai_valuasi', 'label' => 'Nilai Valuasi', 'unit' => 'Rp', 'decimals' => 2],
                    ['key' => 'nilai_pendapatan', 'label' => 'Nilai Pendapatan', 'unit' => 'Rp', 'decimals' => 2],
                ],
            ],
            ['key' => 'luas_area_konservasi', 'label' => 'Luas Area Konservasi', 'unit' => 'ha', 'decimals' => 2],
            ['key' => 'luas_area_direhabilitasi', 'label' => 'Luas Area Direhabilitasi', 'unit' => 'ha', 'decimals' => 2],
            [
                // Children discovered from the data: activity → commodity.
                'key' => 'dampak_ekonomi_produksi', 'label' => 'Dampak Ekonomi (Produksi)', 'unit' => 'kg', 'decimals' => 2,
                'children_sum_to_total' => true,
            ],
            [
                'key' => 'dampak_ekonomi_unit_terjual', 'label' => 'Dampak Ekonomi (Unit Terjual)', 'unit' => 'unit', 'decimals' => 0,
                'children_sum_to_total' => true,
            ],
            $this->peopleMetric('dilatih', 'Total Orang Dilatih'),
            $this->peopleMetric('terlibat', 'Total Orang Terlibat'),
            // Megagrams of carbon (1 Mg C = 1 tonne of carbon), confirmed with
            // the COAST data owner — the column name says "nilai" and declares
            // no unit of its own, so this is not inferable from the schema.
            ['key' => 'nilai_stok_karbon', 'label' => 'Nilai Stok Karbon', 'unit' => 'Mg C', 'decimals' => 2],
        ];
    }

    /** A "people trained/involved" total with its five overlapping categories. */
    private function peopleMetric(string $kind, string $label): array
    {
        return [
            'key' => "orang_{$kind}_total",
            'label' => $label,
            'unit' => 'orang',
            'decimals' => 0,
            // The categories overlap and may be left blank while the total is
            // filled — see the DEMOGRAPHICS docblock.
            'children_sum_to_total' => false,
            'children' => array_map(fn (string $group) => [
                'key' => "orang_{$kind}_{$group}",
                'label' => self::DEMOGRAPHICS[$group],
                'unit' => 'orang',
                'decimals' => 0,
            ], array_keys(self::DEMOGRAPHICS)),
        ];
    }
}
