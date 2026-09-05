<?php

namespace Tests\Feature\Pages;

use Illuminate\Support\Facades\File;
use Tests\TenantTestCase;

/**
 * Every endpoint a page fetches from must actually answer.
 *
 * This exists because the news form kept requesting
 * /dash-api/v1/taxonomy/news_categories after categories had moved to their own
 * relational endpoint. The page rendered fine, the tests passed, and the only
 * symptom was "Gagal memuat kategori" in the browser — exactly the kind of
 * breakage a render-only smoke test cannot see.
 */
class PageEndpointsResolveTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsSuperAdmin();
    }

    /** Endpoints referenced from Blade, extracted from the rendered pages. */
    private function endpointsIn(string $html): array
    {
        preg_match_all('#/dash-api/v1/[A-Za-z0-9\-_/]+#', $html, $matches);

        return array_values(array_unique($matches[0]));
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function pages(): array
    {
        return [
            'dasbor' => ['rekam', 'dashboard'],
            'berita index' => ['rekam', 'news.index'],
            'berita form' => ['rekam', 'news.create'],
            'events index' => ['rekam', 'events.index'],
            'events form' => ['rekam', 'events.create'],
            'tim index' => ['rekam', 'team.index'],
            'tim form' => ['rekam', 'team.create'],
            'partner index' => ['rekam', 'partners.index'],
            'unit index' => ['rekam', 'units.index'],
            'kontak index' => ['rekam', 'contacts.index'],
            'pengguna index' => ['rekam', 'users.index'],
            'pengguna form' => ['rekam', 'users.create'],
            'peran form' => ['rekam', 'roles.create'],
            'company index' => ['rekam', 'tenants.index'],
            'publikasi index' => ['perikanan', 'publications.index'],
            'publikasi form' => ['perikanan', 'publications.create'],
            'milestone index' => ['perikanan', 'milestones.index'],
        ];
    }

    /** @dataProvider pages */
    public function test_every_endpoint_a_page_fetches_from_answers(string $slug, string $route): void
    {
        $this->useTenant($slug === 'rekam' ? $this->rekam : $this->perikanan);

        $html = $this->get(route($route))->assertOk()->getContent();
        $endpoints = $this->endpointsIn($html);

        $this->assertNotEmpty($endpoints, "Halaman {$route} tidak memanggil endpoint apa pun.");

        $broken = [];

        foreach ($endpoints as $endpoint) {
            $status = $this->getJson($endpoint)->getStatusCode();

            // 404 means the page is asking for something that does not exist.
            // 403 would mean the page shows a control the user cannot use.
            if (in_array($status, [403, 404, 500], true)) {
                $broken[] = "{$endpoint} => {$status}";
            }
        }

        $this->assertSame([], $broken, "Halaman {$route} memanggil endpoint yang gagal: ".implode(', ', $broken));
    }

    public function test_no_blade_file_reaches_into_a_parent_scope_through_dollar_root(): void
    {
        // Alpine's $root is a DOM element, so `$root.form.title` is always
        // undefined. Nested x-data inherits the parent scope, so the bare name
        // is the correct access.
        $offenders = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            if (preg_match('/\$root\s*\./', $file->getContents())) {
                $offenders[] = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        $this->assertSame([], $offenders, '$root dipakai untuk mengakses data di: '.implode(', ', $offenders));
    }

    public function test_the_news_category_select_points_at_the_relational_endpoint(): void
    {
        $this->useTenant($this->rekam);

        $html = $this->get(route('news.create'))->getContent();

        $this->assertStringContainsString(route('dash-api.news-categories.index'), $html);
        $this->assertStringNotContainsString('taxonomy/news_categories', $html);
    }
}
