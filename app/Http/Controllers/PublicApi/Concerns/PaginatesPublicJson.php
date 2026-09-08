<?php

namespace App\Http\Controllers\PublicApi\Concerns;

use App\Services\PublicCacheService;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The public counterpart to DashApi\Concerns\PaginatesJson — same {data,meta}
 * envelope, plus the caching and `?fields=` sparse-fieldset steps every
 * public endpoint needs (context.md §4.5, plan.md Fase 6).
 */
trait PaginatesPublicJson
{
    /**
     * @param  class-string  $resourceClass
     */
    protected function listResponse(Request $request, Builder $query, string $resourceClass, string $resource): JsonResponse
    {
        $payload = app(PublicCacheService::class)->remember($resource, $request->query(), function () use ($request, $query, $resourceClass) {
            $perPage = min(
                max((int) $request->query('per_page', config('cms.per_page')), 1),
                config('cms.max_per_page'),
            );

            $page = $query->paginate($perPage);

            return [
                'data' => $resourceClass::collection($page->items())->resolve(),
                'meta' => [
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                    'per_page' => $page->perPage(),
                    'total' => $page->total(),
                ],
            ];
        });

        $payload['data'] = $this->applyFields($payload['data'], $request);

        return response()->json($payload);
    }

    /**
     * A cached, fields-filtered `{data: ...}` response for anything that
     * isn't a paginated list (a single record, a grouped list, taxonomy
     * options, settings). $resolve must throw (e.g. firstOrFail()) rather
     * than return null for "not found" — the exception propagates out of the
     * cache callback uncached, and Laravel's handler turns it into a 404.
     */
    protected function dataResponse(Request $request, string $resource, Closure $resolve): JsonResponse
    {
        $payload = app(PublicCacheService::class)->remember($resource, $request->query(), fn () => ['data' => $resolve()]);

        $payload['data'] = $this->applyFields($payload['data'], $request);

        return response()->json($payload);
    }

    /** `?fields=a,b,c` keeps only those top-level keys, on a list or a single item. */
    private function applyFields(array $data, Request $request): array
    {
        $fields = $request->query('fields');

        if (! $fields) {
            return $data;
        }

        $keep = array_filter(array_map('trim', explode(',', $fields)));

        if (empty($keep)) {
            return $data;
        }

        $filterOne = fn (array $item) => array_intersect_key($item, array_flip($keep));

        return array_is_list($data) ? array_map($filterOne, $data) : $filterOne($data);
    }
}
