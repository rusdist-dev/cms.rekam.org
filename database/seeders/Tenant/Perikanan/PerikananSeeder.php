<?php

namespace Database\Seeders\Tenant\Perikanan;

use Database\Seeders\Tenant\Concerns\SeedsTaxonomy;
use Illuminate\Database\Seeder;

/**
 * Taxonomy specific to perikanan.org (plan.md §5.3).
 *
 * Same columns as rekam, different options — which is the whole point of
 * keeping taxonomy in site_settings rather than in an enum (context.md §5.12).
 */
class PerikananSeeder extends Seeder
{
    use SeedsTaxonomy;

    public function run(): void
    {
        $this->seedTaxonomy('team', 'levels', [
            ['advisor', 'Penasihat', 'Advisor'],
            ['manager', 'Manajer', 'Manager'],
            ['officer', 'Staf', 'Officer'],
        ]);

        // The six marine programmes (plan.md §5.3.a).
        $this->seedTaxonomy('news', 'programs', [
            ['ocean-accounts', 'Ocean Accounts', 'Ocean Accounts'],
            ['sustainable-fisheries', 'Perikanan Berkelanjutan', 'Sustainable Fisheries'],
            ['marine-conservation', 'Konservasi Laut', 'Marine Conservation'],
            ['species-conservation', 'Konservasi Spesies', 'Species Conservation'],
            ['blue-carbon', 'Blue Carbon', 'Blue Carbon'],
            ['ikan', 'IKAN', 'IKAN'],
        ]);

        $this->seedTaxonomy('event', 'categories', [
            ['workshop', 'Lokakarya', 'Workshop'],
            ['seminar', 'Seminar', 'Seminar'],
            ['konferensi', 'Konferensi', 'Conference'],
        ]);

        $this->seedTaxonomy('pub', 'categories', [
            ['laporan', 'Laporan', 'Report'],
            ['panduan', 'Panduan', 'Guideline'],
            ['policy-brief', 'Policy Brief', 'Policy Brief'],
            ['jurnal', 'Jurnal', 'Journal'],
        ]);
    }
}
