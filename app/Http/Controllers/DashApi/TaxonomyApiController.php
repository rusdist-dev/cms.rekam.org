<?php

namespace App\Http\Controllers\DashApi;

use App\Http\Controllers\Controller;
use App\Services\TaxonomyService;
use Illuminate\Http\JsonResponse;

/**
 * Option lists for every select and multi-select in the CMS, read from the
 * active tenant's site_settings (context.md §5.12).
 */
class TaxonomyApiController extends Controller
{
    public function __construct(private readonly TaxonomyService $taxonomy) {}

    public function show(string $group): JsonResponse
    {
        abort_unless(TaxonomyService::isKnown($group), 404, 'Taksonomi tidak ditemukan.');

        return response()->json(['data' => $this->taxonomy->options($group)]);
    }
}
