<?php

namespace App\Http\Controllers\DashApi;

use App\Http\Controllers\Controller;
use App\Services\TaxonomyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    /**
     * Raw, bilingual shape for the taxonomy editor (Pengaturan > Taksonomi) —
     * `show()` above stays flattened to one locale for every content form's
     * select/multi-select, which must not change shape under them.
     */
    public function edit(string $group): JsonResponse
    {
        abort_unless(TaxonomyService::isKnown($group), 404, 'Taksonomi tidak ditemukan.');

        return response()->json(['data' => $this->taxonomy->raw($group)]);
    }

    public function update(Request $request, string $group): JsonResponse
    {
        abort_unless(TaxonomyService::isKnown($group), 404, 'Taksonomi tidak ditemukan.');

        $data = $request->validate([
            'options' => ['required', 'array'],
            'options.*.slug' => ['required', 'string', 'max:64', 'distinct'],
            'options.*.label' => ['array'],
            'options.*.label.id' => ['required', 'string', 'max:255'],
            'options.*.label.en' => ['nullable', 'string', 'max:255'],
        ]);

        $this->taxonomy->replace($group, $data['options']);

        return response()->json(['data' => $this->taxonomy->raw($group)]);
    }
}
