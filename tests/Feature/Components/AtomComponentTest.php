<?php

namespace Tests\Feature\Components;

use App\View\Components\Icon;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * The component library is the contract every page depends on (context.md §3),
 * so a broken atom must fail here rather than in a page nobody rendered yet.
 */
class AtomComponentTest extends TestCase
{
    private function render(string $template, array $data = []): string
    {
        return Blade::render($template, $data);
    }

    public function test_icon_renders_a_known_heroicon(): void
    {
        $html = $this->render('<x-icon name="newspaper" />');

        $this->assertStringContainsString('<svg', $html);
        $this->assertStringContainsString('viewBox="0 0 24 24"', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertStringContainsString('stroke="currentColor"', $html);
    }

    public function test_icon_solid_variant_is_filled(): void
    {
        $html = $this->render('<x-icon name="star" variant="solid" />');

        $this->assertStringContainsString('fill="currentColor"', $html);
        $this->assertStringNotContainsString('stroke-width', $html);
    }

    public function test_icon_falls_back_to_default_size_but_never_conflicts(): void
    {
        // Tailwind resolves conflicting utilities by stylesheet order, so the
        // default size must drop out entirely when the caller supplies one.
        $default = $this->render('<x-icon name="newspaper" />');
        $this->assertStringContainsString('w-5 h-5', $default);

        $sized = $this->render('<x-icon name="newspaper" class="h-6 w-6" />');
        $this->assertStringContainsString('h-6 w-6', $sized);
        $this->assertStringNotContainsString('w-5', $sized);
    }

    public function test_unknown_icon_is_visible_in_debug_and_silent_in_production(): void
    {
        config(['app.debug' => true]);
        $this->assertStringContainsString('?tidak-ada', $this->render('<x-icon name="tidak-ada" />'));

        config(['app.debug' => false]);
        $this->assertSame('', trim($this->render('<x-icon name="tidak-ada" />')));
    }

    public function test_every_registered_icon_renders(): void
    {
        foreach (Icon::available('outline') as $name) {
            $html = $this->render('<x-icon name="'.$name.'" />');
            $this->assertStringContainsString('<svg', $html, "Ikon outline '{$name}' gagal dirender.");
        }

        foreach (Icon::available('solid') as $name) {
            $html = $this->render('<x-icon name="'.$name.'" variant="solid" />');
            $this->assertStringContainsString('<svg', $html, "Ikon solid '{$name}' gagal dirender.");
        }
    }

    public function test_button_variants_use_design_tokens_only(): void
    {
        $html = $this->render('<x-button variant="danger" icon="trash">Hapus</x-button>');

        $this->assertStringContainsString('bg-danger-600', $html);
        $this->assertStringContainsString('Hapus', $html);
        $this->assertStringContainsString('type="button"', $html);
        // context.md §7.1 — no raw hex anywhere in markup.
        $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,6}\b/', $html);
    }

    public function test_button_with_href_renders_an_anchor(): void
    {
        $html = $this->render('<x-button href="/berita">Lihat</x-button>');

        $this->assertStringContainsString('<a href="/berita"', $html);
        $this->assertStringNotContainsString('<button', $html);
    }

    public function test_loading_button_disables_itself_to_block_double_submit(): void
    {
        // context.md §2.6
        $html = $this->render('<x-button loading="saving">Simpan</x-button>');

        $this->assertStringContainsString(':disabled="saving"', $html);
        $this->assertStringContainsString('role="status"', $html);
    }

    public function test_badge_renders_status_variant_with_dot(): void
    {
        $html = $this->render('<x-badge variant="success" dot>Terbit</x-badge>');

        $this->assertStringContainsString('bg-success-50', $html);
        $this->assertStringContainsString('bg-success-500', $html);
        $this->assertStringContainsString('Terbit', $html);
    }

    public function test_card_renders_header_body_and_footer_slots(): void
    {
        $html = $this->render(<<<'BLADE'
            <x-card title="Judul" subtitle="Sub" icon="users">
                <x-slot:actions>AKSI</x-slot:actions>
                ISI
                <x-slot:footer>KAKI</x-slot:footer>
            </x-card>
        BLADE);

        foreach (['Judul', 'Sub', 'AKSI', 'ISI', 'KAKI'] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
    }

    public function test_alert_marks_danger_as_an_assertive_region(): void
    {
        $this->assertStringContainsString('role="alert"', $this->render('<x-alert variant="danger">Galat</x-alert>'));
        $this->assertStringContainsString('role="status"', $this->render('<x-alert variant="info">Info</x-alert>'));
    }

    public function test_avatar_falls_back_to_initials(): void
    {
        $html = $this->render('<x-avatar name="Budi Santoso" />');

        $this->assertStringContainsString('BS', $html);
        $this->assertStringContainsString('aria-label="Budi Santoso"', $html);

        $single = $this->render('<x-avatar name="Ani" />');
        $this->assertStringContainsString('AN', $single);
    }

    public function test_avatar_uses_the_image_when_given(): void
    {
        $html = $this->render('<x-avatar name="Ani" src="/foto.jpg" />');

        $this->assertStringContainsString('<img src="/foto.jpg"', $html);
        $this->assertStringContainsString('alt="Ani"', $html);
    }

    public function test_breadcrumb_marks_the_last_crumb_as_current(): void
    {
        $html = $this->render(
            '<x-breadcrumb :items="$items" />',
            ['items' => [['label' => 'Berita', 'url' => '/berita'], ['label' => 'Tambah']]]
        );

        $this->assertStringContainsString('aria-label="Remah roti"', $html);
        $this->assertStringContainsString('href="/berita"', $html);
        $this->assertMatchesRegularExpression('/aria-current="page"\s*>\s*Tambah/', $html);
    }

    public function test_page_header_renders_title_and_actions(): void
    {
        $html = $this->render(<<<'BLADE'
            <x-page-header title="Berita" subtitle="Kelola berita">
                <x-slot:actions>TOMBOL</x-slot:actions>
            </x-page-header>
        BLADE);

        $this->assertStringContainsString('<h1', $html);
        $this->assertStringContainsString('Berita', $html);
        $this->assertStringContainsString('TOMBOL', $html);
    }

    public function test_empty_state_renders_title_description_and_action(): void
    {
        $html = $this->render(<<<'BLADE'
            <x-empty-state title="Belum ada berita" description="Mulai dari sini." icon="newspaper">
                AKSI
            </x-empty-state>
        BLADE);

        $this->assertStringContainsString('Belum ada berita', $html);
        $this->assertStringContainsString('Mulai dari sini.', $html);
        $this->assertStringContainsString('AKSI', $html);
    }

    public function test_stat_card_binds_its_value_to_alpine_when_driven_by_the_api(): void
    {
        $html = $this->render('<x-stat-card label="Draf" icon="clock" value-bind="stats.draft" loading="loading" />');

        $this->assertStringContainsString('x-text="stats.draft"', $html);
        $this->assertStringContainsString('skeleton', $html);
        $this->assertStringContainsString('x-show="! (loading)"', $html);
    }

    public function test_all_ui_text_in_components_is_indonesian(): void
    {
        // context.md §6.1 — a stray English label is a rejection.
        $forbidden = ['>Save<', '>Delete<', '>Cancel<', '>Loading', '>Search<', 'No data'];

        $html = $this->render(<<<'BLADE'
            <x-spinner />
            <x-empty-state />
            <x-alert variant="warning" dismissible>Perhatian</x-alert>
        BLADE);

        foreach ($forbidden as $needle) {
            $this->assertStringNotContainsString($needle, $html);
        }

        $this->assertStringContainsString('Memuat', $html);
        $this->assertStringContainsString('Belum ada data', $html);
        $this->assertStringContainsString('Tutup pemberitahuan', $html);
    }

    /**
     * <x-form.remote-select> used to build its <select> from two separate
     * literal `class="..."` attributes. Per the HTML parsing spec, a repeated
     * attribute is dropped after the first occurrence, so the browser kept only
     * `class="border-gray-300"` and silently discarded every sizing/padding/
     * shadow/focus utility that came after — the select rendered unstyled next
     * to every other filter on the page, with no error or warning anywhere.
     */
    public function test_remote_select_never_emits_two_class_attributes_on_one_tag(): void
    {
        foreach ([false, true] as $alpine) {
            $html = $this->render(
                '<x-form.remote-select name="category_id" endpoint="/x" model="form.category_id" :alpine="$alpine" />',
                ['alpine' => $alpine]
            );

            $this->assertSame(
                1,
                substr_count($html, '<select'),
                'Render seharusnya memuat tepat satu <select>.'
            );

            preg_match('/<select\b[^>]*>/', $html, $tag);
            $classCount = preg_match_all('/(?<![:\w-])class="/', $tag[0] ?? '');

            $this->assertSame(
                1,
                $classCount,
                "Tag <select> memuat {$classCount} atribut class= (alpine=".($alpine ? 'true' : 'false').') — harus tepat satu.'
            );
        }
    }

    public function test_remote_select_carries_the_same_base_classes_as_static_select(): void
    {
        // The whole point of the component is that a caller cannot tell, by
        // looking at the page, which dropdown fetches its options remotely.
        $remote = $this->render('<x-form.remote-select name="category_id" endpoint="/x" model="form.category_id" size="sm" />');
        $static = $this->render('<x-form.select id="s" size="sm" :options="[]" />');

        foreach (['block', 'w-full', 'rounded', 'border-gray-300', 'shadow-sm', 'py-1.5', 'text-sm'] as $class) {
            $this->assertStringContainsString($class, $remote, "remote-select kehilangan kelas '{$class}'.");
            $this->assertStringContainsString($class, $static, "select kehilangan kelas '{$class}' (rujukan).");
        }
    }
}
