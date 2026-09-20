<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * Keeps docs/openapi.yaml honest about the two token-authenticated API
 * surfaces (`/api/v1/*` and `/api/v1/ext/*`).
 *
 * A specification is only worth having if it cannot quietly drift from the
 * application. Adding a route without documenting it — or leaving a documented
 * path behind after deleting the route — fails here rather than being
 * discovered by whoever wrote a client against it.
 */
class OpenApiSpecTest extends TestCase
{
    private array $spec;

    protected function setUp(): void
    {
        parent::setUp();

        $this->spec = Yaml::parseFile(base_path('docs/openapi.yaml'));
    }

    public function test_every_documented_path_is_a_real_route(): void
    {
        // Guard against the comparison passing because both sides came back
        // empty — two empty sets match, and would prove nothing.
        $this->assertGreaterThan(20, count($this->documented()));

        $this->assertSame([], array_values(array_diff($this->documented(), $this->routed())));
    }

    public function test_every_route_on_these_surfaces_is_documented(): void
    {
        $this->assertSame([], array_values(array_diff($this->routed(), $this->documented())));
    }

    public function test_every_internal_reference_resolves(): void
    {
        preg_match_all(
            '~#/([A-Za-z0-9_/]+)~',
            file_get_contents(base_path('docs/openapi.yaml')),
            $matches,
        );

        $unresolved = [];

        foreach (array_unique($matches[1]) as $ref) {
            $node = $this->spec;

            foreach (explode('/', $ref) as $segment) {
                if (! is_array($node) || ! array_key_exists($segment, $node)) {
                    $unresolved[] = $ref;

                    continue 2;
                }

                $node = $node[$segment];
            }
        }

        $this->assertSame([], $unresolved, 'Referensi menggantung: '.implode(', ', $unresolved));
    }

    public function test_every_tag_used_by_an_operation_is_declared(): void
    {
        $declared = array_column($this->spec['tags'], 'name');
        $used = [];

        foreach ($this->spec['paths'] as $operations) {
            foreach ($operations as $operation) {
                $used = array_merge($used, $operation['tags'] ?? []);
            }
        }

        $undeclared = array_values(array_diff(array_unique($used), $declared));

        $this->assertSame([], $undeclared, 'Tag belum dideklarasikan: '.implode(', ', $undeclared));
    }

    /**
     * Documented paths, as full URIs.
     *
     * The spec's `servers` end at `/api/v1`, so a path of `/ext/coast/desa`
     * describes the route `api/v1/ext/coast/desa`.
     *
     * @return array<int, string>
     */
    private function documented(): array
    {
        $paths = array_map(
            fn (string $path) => 'api/v1'.$path,
            array_keys($this->spec['paths']),
        );

        sort($paths);

        return $paths;
    }

    /**
     * The routes on the two token surfaces — identified by their guard rather
     * than by a URI prefix, so a route that loses `resolve.public.tenant` shows
     * up as undocumented rather than silently leaving the comparison.
     *
     * @return array<int, string>
     */
    private function routed(): array
    {
        $uris = [];

        foreach (Route::getRoutes() as $route) {
            $methods = array_diff($route->methods(), ['HEAD', 'OPTIONS']);

            if ($methods === [] || ! in_array('resolve.public.tenant', $route->gatherMiddleware(), true)) {
                continue;
            }

            $uris[] = $route->uri();
        }

        $uris = array_values(array_unique($uris));
        sort($uris);

        return $uris;
    }
}
