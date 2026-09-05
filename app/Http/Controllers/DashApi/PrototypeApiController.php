<?php

namespace App\Http\Controllers\DashApi;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * PHASE 1 ONLY — serves `resources/prototype/*.json` so the Alpine layer can be
 * built and exercised against real search, filter, sort and pagination before
 * the database exists (plan.md Fase 1).
 *
 * It deliberately mimics the response envelope the real controllers must use
 * (context.md §4.5), so replacing it module by module in Fase 3+ changes no
 * front-end code. Delete this class once every module has its own controller.
 */
class PrototypeApiController extends Controller
{
    /** Fields searched by the `search` query parameter, per resource. */
    private const SEARCHABLE = [
        'news' => ['title.id', 'title.en', 'author_name'],
        'events' => ['title.id', 'location.id'],
        'team' => ['name', 'position.id', 'email'],
        'publications' => ['title.id', 'file_name'],
        'partners' => ['name', 'url'],
        'contacts' => ['name', 'email', 'subject', 'message'],
        'milestones' => ['title.id'],
        'units' => ['name', 'domain'],
        'users' => ['name', 'email'],
        'roles' => ['name', 'label'],
    ];

    /** Exact-match filters accepted per resource. */
    private const FILTERABLE = [
        'news' => ['status', 'category_id' => 'category.id', 'program' => 'related_programs'],
        'events' => ['status', 'category'],
        'team' => ['group', 'is_active'],
        'publications' => ['category', 'is_featured'],
        'partners' => ['is_active'],
        'contacts' => ['status'],
        'milestones' => ['year', 'is_active'],
        'units' => ['is_active'],
        'users' => ['role', 'is_active'],
    ];

    public function index(Request $request): JsonResponse
    {
        $resource = $this->resource($request);
        $items = $this->load($resource);

        $items = $this->applySearch($items, $resource, (string) $request->query('search', ''));
        $items = $this->applyFilters($items, $resource, $request);
        $items = $this->applySort($items, $request);

        return $this->paginate($items, $request);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $item = $this->load($this->resource($request))->firstWhere('id', (int) $id);

        abort_if($item === null, 404, 'Data tidak ditemukan.');

        return response()->json(['data' => $item]);
    }

    /** Dashboard widgets read one named section of stats.json. */
    public function stats(string $section): JsonResponse
    {
        $stats = $this->load('stats');

        abort_if(! $stats->has($section), 404, 'Data statistik tidak ditemukan.');

        return response()->json(['data' => $stats->get($section)]);
    }

    /** Taxonomy options — in production these come from site_settings. */
    public function taxonomy(string $group): JsonResponse
    {
        $taxonomy = $this->load('taxonomy');

        abort_if(! $taxonomy->has($group), 404, 'Taksonomi tidak ditemukan.');

        return response()->json(['data' => $taxonomy->get($group)]);
    }

    /**
     * Which fixture this route serves. Read from the route's defaults rather
     * than a method argument: Laravel fills unmatched scalar parameters
     * positionally, so `show($resource, $id)` would receive the URI id.
     */
    private function resource(Request $request): string
    {
        return (string) ($request->route()->defaults['resource'] ?? '');
    }

    private function load(string $resource): Collection
    {
        // Guard against traversal: only the fixtures shipped in the repo.
        abort_unless(preg_match('/^[a-z0-9-]+$/', $resource) === 1, 404);

        $path = resource_path("prototype/{$resource}.json");

        abort_unless(is_file($path), 404, 'Sumber data tidak ditemukan.');

        return collect(json_decode((string) file_get_contents($path), true));
    }

    private function applySearch(Collection $items, string $resource, string $term): Collection
    {
        $term = trim($term);

        if ($term === '') {
            return $items;
        }

        $fields = self::SEARCHABLE[$resource] ?? [];

        return $items->filter(function (array $item) use ($fields, $term) {
            foreach ($fields as $field) {
                if (str_contains(mb_strtolower((string) Arr::get($item, $field)), mb_strtolower($term))) {
                    return true;
                }
            }

            return false;
        })->values();
    }

    private function applyFilters(Collection $items, string $resource, Request $request): Collection
    {
        foreach (self::FILTERABLE[$resource] ?? [] as $param => $path) {
            // Numeric key means the parameter and the field share a name.
            if (is_int($param)) {
                $param = $path;
            }

            $value = $request->query($param);

            if ($value === null || $value === '') {
                continue;
            }

            $items = $items->filter(function (array $item) use ($path, $value) {
                $actual = Arr::get($item, $path);

                // Array columns (related_programs) match on membership.
                if (is_array($actual)) {
                    return in_array($value, $actual, false);
                }

                if (is_bool($actual)) {
                    return $actual === filter_var($value, FILTER_VALIDATE_BOOLEAN);
                }

                return (string) $actual === (string) $value;
            })->values();
        }

        return $items;
    }

    private function applySort(Collection $items, Request $request): Collection
    {
        $sort = $request->query('sort');

        if (! $sort) {
            return $items->values();
        }

        $descending = $request->query('direction', 'asc') === 'desc';

        return $items
            ->sortBy(fn (array $item) => Arr::get($item, $sort), SORT_NATURAL | SORT_FLAG_CASE, $descending)
            ->values();
    }

    private function paginate(Collection $items, Request $request): JsonResponse
    {
        $perPage = min(
            max((int) $request->query('per_page', config('cms.per_page')), 1),
            config('cms.max_per_page')
        );

        $total = $items->count();
        $lastPage = max((int) ceil($total / $perPage), 1);
        $page = min(max((int) $request->query('page', 1), 1), $lastPage);

        return response()->json([
            'data' => $items->forPage($page, $perPage)->values(),
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ]);
    }
}
