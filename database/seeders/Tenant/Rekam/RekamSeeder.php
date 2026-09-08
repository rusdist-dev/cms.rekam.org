<?php

namespace Database\Seeders\Tenant\Rekam;

use Database\Seeders\Tenant\Concerns\SeedsTaxonomy;
use Illuminate\Database\Seeder;

/**
 * Taxonomy specific to rekam.org (plan.md §5.2).
 *
 * This file is reached by slug→folder mapping in tenants:seed, the same
 * sanctioned mechanism migrations use — not by an `if ($tenant->slug === ...)`
 * anywhere in the application (context.md §5.8).
 */
class RekamSeeder extends Seeder
{
    use SeedsTaxonomy;

    public function run(): void
    {
        // Display order of the groups on the team page (plan.md §5.2.b).
        $this->seedTaxonomy('team', 'levels', [
            ['advisor-board', 'Dewan Penasihat', 'Advisor Board'],
            ['supervisor-board', 'Dewan Pengawas', 'Supervisor Board'],
            ['chairperson', 'Ketua', 'Chairperson'],
            ['director', 'Direktur', 'Director'],
            ['manager', 'Manajer', 'Manager'],
        ]);

        $this->seedTaxonomy('news', 'programs', [
            ['forest', 'Forest', 'Forest'],
            ['urban', 'Urban', 'Urban'],
            ['ocean', 'Ocean', 'Ocean'],
        ]);

        $this->seedTaxonomy('event', 'categories', [
            ['workshop', 'Lokakarya', 'Workshop'],
            ['seminar', 'Seminar', 'Seminar'],
            ['pelatihan', 'Pelatihan', 'Training'],
            ['diskusi', 'Diskusi Publik', 'Public Discussion'],
            ['kunjungan', 'Kunjungan Lapangan', 'Field Trip'],
        ]);

        // Confirmed 2026-09-08: rekam.org now runs Publikasi too — same
        // starting categories as perikanan (plan.md §5.3.a), an editor can
        // rename/add from here without a deploy (context.md §5.12).
        $this->seedTaxonomy('pub', 'categories', [
            ['laporan', 'Laporan', 'Report'],
            ['panduan', 'Panduan', 'Guideline'],
            ['policy-brief', 'Policy Brief', 'Policy Brief'],
            ['jurnal', 'Jurnal', 'Journal'],
        ]);
    }
}
