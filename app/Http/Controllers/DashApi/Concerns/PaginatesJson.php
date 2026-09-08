<?php

namespace App\Http\Controllers\DashApi\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The list envelope every internal endpoint must return (context.md §4.5).
 * Keeping it in one place is what lets the front-end treat every module the
 * same way.
 */
trait PaginatesJson
{
    protected function perPage(Request $request): int
    {
        return min(
            max((int) $request->query('per_page', config('cms.per_page')), 1),
            config('cms.max_per_page')
        );
    }

    /**
     * Paginates and clamps the page number.
     *
     * Laravel happily returns page 9999 of 3 as an empty set with
     * `current_page: 9999`, which the pagination component then renders as
     * "Halaman 9999 dari 3". Deleting the last row on the last page is enough
     * to land there, so the page is pulled back into range instead.
     */
    protected function paginate(Builder $query, Request $request, string $resource): JsonResponse
    {
        $perPage = $this->perPage($request);
        $page = $query->paginate($perPage);

        if ($page->currentPage() > $page->lastPage()) {
            $page = $query->paginate($perPage, ['*'], 'page', $page->lastPage());
        }

        return $this->paginated($page, $resource);
    }

    /**
     * @param  class-string  $resource
     */
    protected function paginated(LengthAwarePaginator $page, string $resource): JsonResponse
    {
        return response()->json([
            'data' => $resource::collection($page->items())->resolve(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /**
     * The same {data, meta} envelope as paginate(), but for a module driven by
     * resources/js/alpine/sortableList.js — that client never sends `page`,
     * it expects `data` to already be the whole ordered set (dragging item 16
     * into position would otherwise be silently impossible: paginate() here
     * would truncate the list to config('cms.per_page') rows before the drag
     * handler ever sees the rest). `meta` stays for envelope consistency with
     * every other endpoint (context.md §4.5) even though this client ignores it.
     */
    protected function full(Collection $items, string $resource): JsonResponse
    {
        return response()->json([
            'data' => $resource::collection($items)->resolve(),
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => max($items->count(), 1),
                'total' => $items->count(),
            ],
        ]);
    }

    /**
     * Applies a whitelisted sort. Anything not on the list falls back to the
     * default rather than reaching the query builder, so `?sort=` can never
     * name an arbitrary column.
     */
    protected function applySort(Builder $query, Request $request, array $allowed, string $default, string $defaultDirection = 'desc'): Builder
    {
        $sort = $request->query('sort');
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        if (! is_string($sort) || ! array_key_exists($sort, $allowed)) {
            return $query->orderBy($default, $defaultDirection);
        }

        return $query->orderBy($allowed[$sort], $direction);
    }
}
