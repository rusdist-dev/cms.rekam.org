<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The hard rules in context.md are meant to be enforced at review time. These
 * checks make the cheap, mechanical ones fail in CI instead, so a violation
 * never depends on a reviewer noticing it.
 */
class ContextComplianceTest extends TestCase
{
    /** @return array<string, string> path => contents */
    private function blades(): array
    {
        return collect(File::allFiles(resource_path('views')))
            ->mapWithKeys(fn ($f) => [
                str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $f->getPathname()) => $f->getContents(),
            ])
            ->all();
    }

    /** @return array<string, string> */
    private function scripts(): array
    {
        return collect(File::allFiles(resource_path('js')))
            ->filter(fn ($f) => $f->getExtension() === 'js')
            ->mapWithKeys(fn ($f) => [
                str_replace(resource_path('js').DIRECTORY_SEPARATOR, '', $f->getPathname()) => $f->getContents(),
            ])
            ->all();
    }

    /**
     * Comments legitimately quote the very things these checks ban ("never call
     * fetch() directly"), so they are removed before scanning for real code.
     */
    private function stripComments(string $contents): string
    {
        $contents = preg_replace('/\{\{--.*?--\}\}/s', '', $contents);      // Blade
        $contents = preg_replace('/\/\*.*?\*\//s', '', $contents);          // block
        $contents = preg_replace('/(^|\s)\/\/[^\r\n]*/m', '$1', $contents); // line
        $contents = preg_replace('/(^|\s)#[^\r\n]*/m', '$1', $contents);    // PHP hash

        return $contents;
    }

    public function test_no_php_style_comment_contains_literal_blade_syntax(): void
    {
        // Blade's compiler expands `<x-tag>` and `{{ ... }}` textually,
        // wherever they appear in the raw file — including inside a plain PHP
        // `//` or `*` comment (only a real `{{-- Blade --}}` comment is
        // stripped before that scan runs). A doc comment that quotes either
        // as a literal example — "passed through to <x-modal> for Escape" or
        // "defaults to `{{ $show }} = false`" — gets silently expanded into
        // real PHP mid-comment, corrupting the surrounding syntax. Both really
        // happened here: one broke every page that renders <x-modal.confirm>
        // (an undefined $component error), the other was a syntax error in
        // @props' array literal. Neither surfaced in a diff — only at runtime.
        $offenders = [];

        foreach ($this->blades() as $path => $contents) {
            foreach (explode("\n", $contents) as $i => $line) {
                $trimmed = ltrim($line);

                if (! str_starts_with($trimmed, '//') && ! str_starts_with($trimmed, '*')) {
                    continue;
                }

                if (preg_match('/<x-[a-zA-Z][\w.:-]*\s*\/?>/', $line)
                    || preg_match('/\{\{.*?\}\}|\{!!.*?!!\}/', $line)) {
                    $offenders[] = $path.':'.($i + 1);
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'Komentar PHP memuat sintaks Blade literal (<x-tag> atau {{ }}), yang akan dikompilasi sungguhan: '.
            implode(', ', $offenders)
        );
    }

    public function test_no_script_clones_alpine_reactive_data_with_structured_clone(): void
    {
        // structuredClone() throws DataCloneError ("#<Object> could not be
        // cloned") the moment the value has passed through Alpine's reactivity
        // Proxy — which every property read off x-data has. resources/js/clone.js
        // (a JSON round trip) is the only thing that actually works here, and is
        // provably correct because our form state is always JSON-safe.
        $offenders = [];

        foreach ($this->scripts() as $path => $contents) {
            if ($path === 'clone.js') {
                continue;
            }

            if (preg_match('/\bstructuredClone\s*\(/', $this->stripComments($contents))) {
                $offenders[] = $path;
            }
        }

        $this->assertSame([], $offenders, 'structuredClone() dipakai di: '.implode(', ', $offenders).' — pakai clone() dari resources/js/clone.js.');
    }

    public function test_every_modal_with_a_computed_show_expression_declares_on_close(): void
    {
        // <x-modal>'s Cancel/Escape/X actions default to "{{ $show }} = false"
        // — only valid Alpine when `show` is a bare variable name. The
        // delete-confirmation pattern passes `show="confirming !== null"`
        // (`confirming` holds an id or null, not a plain boolean), so that
        // default compiles to "confirming !== null = false" — Alpine throws
        // "Invalid left-hand side in assignment" the instant Cancel, Escape, or
        // the X button fires, and the modal never closes. Any `show` that is
        // not a single identifier must supply `on-close` explicitly.
        $offenders = [];

        foreach ($this->blades() as $path => $contents) {
            if (! preg_match_all('/<x-modal(?:\.confirm)?\s+([^>]*?)\/?>/s', $contents, $tags, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($tags as $tag) {
                $attrs = $tag[1];

                if (! preg_match('/\bshow="([^"]*)"/', $attrs, $showMatch)) {
                    continue;
                }

                $isBareIdentifier = (bool) preg_match('/^[A-Za-z_$][A-Za-z0-9_$]*$/', trim($showMatch[1]));

                if (! $isBareIdentifier && ! preg_match('/\bon-close="/', $attrs)) {
                    $offenders[] = "{$path} (show=\"{$showMatch[1]}\")";
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "show berupa ekspresi tanpa on-close, Cancel/Escape/X akan error 'Invalid left-hand side in assignment': ".implode(', ', $offenders)
        );
    }

    public function test_no_stateful_alpine_factory_is_spread_into_another_object_literal(): void
    {
        // `{ ...resourceTable(endpoint, ...), editUrl: '...' }` looks harmless
        // but is not: spread reads every property via [[Get]], so a getter
        // (isEmpty, isReady, isEditing, hasErrors, ...) is invoked ONCE, right
        // then — before init() has fetched anything — and what lands on the
        // new object is the plain value it happened to return at that instant,
        // never the getter itself. isEmpty freezes to `true` forever (items
        // was still `[]`), so a table's empty state never leaves even once
        // real data arrives — exactly the bug this check exists to catch.
        // Object.assign() has the identical failure mode.
        //
        // The fix is resources/js/extend.js (Object.defineProperties +
        // Object.getOwnPropertyDescriptors, which copies the accessor itself
        // rather than invoking it) — every one of these factories accepts a
        // page's extra properties through its own `options.extra` instead.
        $factories = ['resourceTable', 'resourceForm', 'sortableList', 'apiResource', 'teamBoard', 'contentForm', 'roleForm', 'tenantForm', 'remoteSelect'];
        $pattern = '/\.\.\.\s*('.implode('|', $factories).')\s*\(/';

        $offenders = [];

        foreach (array_merge($this->blades(), $this->scripts()) as $path => $contents) {
            if (preg_match($pattern, $this->stripComments($contents))) {
                $offenders[] = $path;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'Factory Alpine di-spread ke literal lain (bekukan getter): '.implode(', ', $offenders).
            ' — panggil langsung dengan options.extra, lihat resources/js/extend.js.'
        );
    }

    public function test_no_forbidden_http_client_is_installed(): void
    {
        // context.md §2.1
        $package = json_decode(File::get(base_path('package.json')), true);
        $deps = array_merge($package['dependencies'] ?? [], $package['devDependencies'] ?? []);

        foreach (['axios', 'jquery', 'superagent', 'ky'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $deps, "Paket '{$forbidden}' dilarang (context.md §2.1).");
        }
    }

    public function test_no_spa_framework_is_installed(): void
    {
        // context.md §1.2
        $package = json_decode(File::get(base_path('package.json')), true);
        $deps = array_merge($package['dependencies'] ?? [], $package['devDependencies'] ?? []);
        $composer = json_decode(File::get(base_path('composer.json')), true);
        $php = array_merge($composer['require'] ?? [], $composer['require-dev'] ?? []);

        foreach (['react', 'vue', 'svelte', '@inertiajs/inertia'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $deps);
        }

        foreach (['inertiajs/inertia-laravel', 'livewire/livewire'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $php);
        }
    }

    public function test_blade_files_contain_no_raw_svg(): void
    {
        // context.md §3.5 — icons only through <x-icon>, which owns the one
        // <svg> in components/icon.blade.php.
        $offenders = [];

        foreach ($this->blades() as $path => $contents) {
            if ($path === 'components'.DIRECTORY_SEPARATOR.'icon.blade.php') {
                continue;
            }

            if (str_contains($contents, '<svg')) {
                $offenders[] = $path;
            }
        }

        $this->assertSame([], $offenders, 'Ada <svg> mentah di: '.implode(', ', $offenders));
    }

    public function test_blade_files_contain_no_raw_hex_colours_or_inline_styles(): void
    {
        // context.md §3.8 and §7.1
        $hexOffenders = [];
        $styleOffenders = [];

        foreach ($this->blades() as $path => $contents) {
            // Arbitrary-value Tailwind colours, e.g. bg-[#0d9488].
            if (preg_match('/(bg|text|border|ring|fill|stroke)-\[#[0-9a-fA-F]{3,8}\]/', $contents)) {
                $hexOffenders[] = $path;
            }

            if (preg_match('/<style[\s>]/i', $contents)) {
                $styleOffenders[] = $path;
            }
        }

        $this->assertSame([], $hexOffenders, 'Warna hex mentah di: '.implode(', ', $hexOffenders));
        $this->assertSame([], $styleOffenders, '<style> inline di: '.implode(', ', $styleOffenders));
    }

    public function test_blade_files_never_call_fetch_directly(): void
    {
        // context.md §2.4 — every request goes through window.api.
        $offenders = [];

        foreach ($this->blades() as $path => $contents) {
            if (preg_match('/(?<!window\.api\.)\bfetch\s*\(/', $this->stripComments($contents))) {
                $offenders[] = $path;
            }
        }

        $this->assertSame([], $offenders, 'fetch() mentah di Blade: '.implode(', ', $offenders));
    }

    public function test_javascript_never_calls_fetch_outside_the_api_helper(): void
    {
        $offenders = [];

        foreach ($this->scripts() as $path => $contents) {
            if ($path === 'api.js') {
                continue;
            }

            if (preg_match('/\bfetch\s*\(/', $this->stripComments($contents))) {
                $offenders[] = $path;
            }
        }

        $this->assertSame([], $offenders, 'fetch() di luar api.js: '.implode(', ', $offenders));
    }

    public function test_no_browser_confirm_or_alert_anywhere(): void
    {
        // context.md §2.7 and §7.11
        foreach ($this->blades() + $this->scripts() as $path => $contents) {
            $this->assertDoesNotMatchRegularExpression(
                '/(?<![\w.$])(confirm|alert)\s*\(/',
                $this->stripComments($contents),
                "confirm()/alert() bawaan browser dipakai di {$path}."
            );
        }
    }

    public function test_no_debug_statements_are_left_behind(): void
    {
        // context.md §8.10
        foreach ($this->blades() + $this->scripts() as $path => $contents) {
            $clean = $this->stripComments($contents);

            // Word boundaries matter: Alpine's own add() ends in "dd(".
            foreach (['console\.log', 'dd', 'dump', 'var_dump'] as $needle) {
                $this->assertDoesNotMatchRegularExpression(
                    '/(?<![\w.$-])'.$needle.'\s*\(/',
                    $clean,
                    "Sisa debug '{$needle}(' di {$path}."
                );
            }
        }
    }

    public function test_no_cdn_script_or_stylesheet_in_blade(): void
    {
        // context.md §1.6 — Vite only. Google Fonts' stylesheet is the one
        // allowed external link and is not a script.
        foreach ($this->blades() as $path => $contents) {
            $this->assertDoesNotMatchRegularExpression(
                '/<script[^>]+src="https?:\/\//i',
                $contents,
                "CDN <script> di {$path}."
            );
        }
    }

    public function test_pages_stay_under_the_size_limit(): void
    {
        // context.md §3.6 — a page over ~150 lines should have become components.
        $offenders = [];

        foreach ($this->blades() as $path => $contents) {
            if (str_starts_with($path, 'components'.DIRECTORY_SEPARATOR)) {
                continue;
            }

            $lines = substr_count($contents, "\n") + 1;

            if ($lines > 150) {
                $offenders[] = "{$path} ({$lines} baris)";
            }
        }

        $this->assertSame([], $offenders, 'Halaman melebihi 150 baris: '.implode(', ', $offenders));
    }

    public function test_no_long_inline_script_blocks_in_blade(): void
    {
        // context.md §3.9 — Alpine logic longer than a few lines lives in
        // resources/js/alpine/.
        $offenders = [];

        foreach ($this->blades() as $path => $contents) {
            if (preg_match_all('/<script(?![^>]*src=)[^>]*>(.*?)<\/script>/s', $contents, $m)) {
                foreach ($m[1] as $block) {
                    if (substr_count(trim($block), "\n") > 5) {
                        $offenders[] = $path;
                    }
                }
            }
        }

        $this->assertSame([], $offenders, 'Blok <script> panjang di: '.implode(', ', $offenders));
    }

    public function test_business_logic_never_branches_on_a_tenant_slug(): void
    {
        // context.md §5.8 — branch on features, never on identity. TenantsMigrate
        // is the single sanctioned exception, and does not exist yet.
        $offenders = [];

        foreach (File::allFiles(app_path()) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = $file->getContents();

            if (preg_match('/slug\s*===?\s*[\'"](rekam|perikanan)[\'"]/', $contents)) {
                $offenders[] = $file->getFilename();
            }
        }

        $this->assertSame([], $offenders, 'Pengecekan slug tenant di: '.implode(', ', $offenders));
    }

    public function test_tenant_database_names_are_never_hardcoded(): void
    {
        // context.md §5.2
        $offenders = [];

        foreach (array_merge(File::allFiles(app_path()), File::allFiles(config_path())) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            if (preg_match('/[\'"]cms_(rekam|perikanan)[\'"]/', $file->getContents())) {
                $offenders[] = $file->getFilename();
            }
        }

        $this->assertSame([], $offenders, 'Nama DB tenant di-hardcode di: '.implode(', ', $offenders));
    }

    public function test_every_form_input_component_supports_a_label(): void
    {
        // context.md §7.9 — every input needs a <label>; the field wrapper is
        // what provides it, so it must exist and render one.
        $field = File::get(resource_path('views/components/form/field.blade.php'));

        $this->assertStringContainsString('<label', $field);
        $this->assertStringContainsString('for="{{ $for }}"', $field);
    }
}
