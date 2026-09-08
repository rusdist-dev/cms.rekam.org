<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PublicApi\Concerns\PaginatesPublicJson;
use App\Http\Resources\Public\PublicationResource;
use App\Models\Publication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicationController extends Controller
{
    use PaginatesPublicJson;

    public function index(Request $request): JsonResponse
    {
        // No visibility flag exists on this model — every row is public,
        // same as the internal index (confirmed: only is_featured/category).
        $query = Publication::query()
            ->category($request->query('category'))
            ->featured($request->query('featured'))
            ->orderBy('sort_order');

        return $this->listResponse($request, $query, PublicationResource::class, 'publications');
    }
}
