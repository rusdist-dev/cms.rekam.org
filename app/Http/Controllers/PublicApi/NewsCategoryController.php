<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PublicApi\Concerns\PaginatesPublicJson;
use App\Http\Resources\Public\NewsCategoryResource;
use App\Models\NewsCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsCategoryController extends Controller
{
    use PaginatesPublicJson;

    public function index(Request $request): JsonResponse
    {
        return $this->dataResponse($request, 'news-categories', fn () => NewsCategoryResource::collection(
            NewsCategory::ordered()->get(),
        )->resolve());
    }
}
