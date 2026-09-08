<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PublicApi\Concerns\PaginatesPublicJson;
use App\Services\TaxonomyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgramController extends Controller
{
    use PaginatesPublicJson;

    public function __construct(private readonly TaxonomyService $taxonomy) {}

    public function index(Request $request): JsonResponse
    {
        return $this->dataResponse($request, 'programs', fn () => $this->taxonomy->options('news_programs'));
    }
}
