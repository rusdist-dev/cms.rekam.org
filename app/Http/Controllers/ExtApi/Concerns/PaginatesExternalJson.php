<?php

namespace App\Http\Controllers\ExtApi\Concerns;

use App\Services\ExternalCacheService;
use Closure;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The external-API counterpart of PublicApi\Concerns\PaginatesPublicJson —
 * same {data, meta} envelope and same ?fields= behaviour, so a consumer that
 * already speaks /api/v1/* needs no new client code for /api/v1/ext/*.
 *
 * Differences are the two that matter for a foreign database: the cache is
 * keyed by datasource rather than by tenant (see ExternalCacheService), and the
 * builder is the query builder's contract rather than Eloquent's, because a
 * legacy schema is often not worth a model.
 */
trait PaginatesExternalJson
{
    /**
     * @param  class-string|null  $resourceClass  null returns rows as-is
     */
    protected function listResponse(Request $request, Builder $query, ?string $resourceClass, string $resource): JsonResponse
    {
        $payload = app(ExternalCacheService::class)->remember(
            $this->datasource,
            $resource,
            $request->query(),
            function () use ($request, $query, $resourceClass) {
                $perPage = min(
                    max((int) $request->query('per_page', config('cms.per_page')), 1),
                    config('cms.max_per_page'),
                );

                $page = $query->paginate($perPage);

                return [
                    'data' => $resourceClass
                        ? $resourceClass::collection($page->items())->resolve()
                        : array_map($this->toArray(...), $page->items()),
                    'meta' => [
                        'current_page' => $page->currentPage(),
                        'last_page' => $page->lastPage(),
                        'per_page' => $page->perPage(),
                        'total' => $page->total(),
                    ],
                ];
            }
        );

        $payload['data'] = $this->applyFields($payload['data'], $request);

        return response()->json($payload);
    }

    /**
     * A cached, fields-filtered `{data: ...}` response for anything that is not
     * a paginated list. As in PaginatesPublicJson, $resolve must throw for
     * "not found" rather than return null — the exception escapes the cache
     * callback uncached and Laravel's handler turns it into a 404.
     */
    protected function dataResponse(Request $request, string $resource, Closure $resolve): JsonResponse
    {
        $payload = app(ExternalCacheService::class)->remember(
            $this->datasource,
            $resource,
            $request->query(),
            fn () => ['data' => $resolve()],
        );

        $payload['data'] = $this->applyFields($payload['data'], $request);

        return response()->json($payload);
    }

    /**
     * A row without a Resource class. Query-builder rows are stdClass and cast
     * cleanly; an Eloquent model does not — casting one to array yields its
     * protected properties under null-byte-mangled keys, so it has to be asked
     * for its own array form instead.
     */
    private function toArray(mixed $row): array
    {
        return $row instanceof Arrayable ? $row->toArray() : (array) $row;
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
