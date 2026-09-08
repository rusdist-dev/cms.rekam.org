<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PublicApi\Concerns\PaginatesPublicJson;
use App\Http\Resources\Public\UnitResource;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    use PaginatesPublicJson;

    public function index(Request $request): JsonResponse
    {
        // Not `->active()` — that scope takes an optional query-string value
        // for the admin index; the public API always hard-filters.
        $query = Unit::query()->where('is_active', true)->orderBy('sort_order');

        return $this->listResponse($request, $query, UnitResource::class, 'units');
    }
}
