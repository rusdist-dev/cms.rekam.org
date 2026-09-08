<?php

namespace App\Http\Controllers\DashApi\Concerns;

use App\Services\PublicCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Batch `sort_order` persistence for drag-and-drop lists (team, partners).
 *
 * The client always sends the whole resulting order rather than a single
 * moved id, so this never has to infer position from a partial payload
 * (resources/js/alpine/sortableList.js).
 */
trait ReordersRows
{
    /**
     * @param  class-string  $modelClass
     * @param  string|null  $publicResource  the public API cache key this
     *                                       model's list is stored under (e.g. 'team'), so a reorder — which
     *                                       bypasses the content Service entirely — still invalidates it.
     */
    protected function reorderRows(Request $request, string $modelClass, string $ability, ?string $publicResource = null): JsonResponse
    {
        $this->authorize($ability, $modelClass);

        $table = (new $modelClass)->getTable();

        $data = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            // "tenant." matters: an exists rule defaults to the central
            // connection, where this table does not exist (context.md §5.1).
            'order.*.id' => ['required', 'integer', Rule::exists("tenant.{$table}", 'id')],
            'order.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        DB::connection('tenant')->transaction(function () use ($modelClass, $data) {
            foreach ($data['order'] as $row) {
                $modelClass::whereKey($row['id'])->update(['sort_order' => $row['sort_order']]);
            }
        });

        if ($publicResource !== null) {
            app(PublicCacheService::class)->forget($publicResource);
        }

        return response()->json(['message' => 'Urutan diperbarui.']);
    }
}
