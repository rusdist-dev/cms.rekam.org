<?php

namespace App\Http\Controllers\DashApi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSiteSeoRequest;
use App\Models\SiteSetting;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;

/**
 * Default SEO metadata, used when a content record has no meta of its own —
 * a singleton per tenant, same shape as SiteIdentityApiController.
 */
class SiteSeoApiController extends Controller
{
    public function __construct(private readonly MediaService $media) {}

    public function show(): JsonResponse
    {
        $value = SiteSetting::get('site', 'seo', [
            'meta_title' => ['id' => '', 'en' => ''],
            'meta_description' => ['id' => '', 'en' => ''],
            'og_image_path' => null,
            'keywords' => [],
        ]);

        return response()->json(['data' => $this->present($value)]);
    }

    public function update(UpdateSiteSeoRequest $request): JsonResponse
    {
        $data = $request->validated();

        $current = SiteSetting::get('site', 'seo', []);
        $ogImagePath = $current['og_image_path'] ?? null;

        if ($request->file('og_image')) {
            $ogImagePath = $this->media->storeImage($request->file('og_image'), 'seo');

            if ($current['og_image_path'] ?? null) {
                $this->media->delete($current['og_image_path']);
            }
        } elseif ($request->boolean('remove_og_image')) {
            if ($ogImagePath) {
                $this->media->delete($ogImagePath);
            }

            $ogImagePath = null;
        }

        $value = [
            'meta_title' => $data['meta_title'] ?? ['id' => null, 'en' => null],
            'meta_description' => $data['meta_description'] ?? ['id' => null, 'en' => null],
            'og_image_path' => $ogImagePath,
            'keywords' => array_values($data['keywords'] ?? []),
        ];

        SiteSetting::put('site', 'seo', $value);

        return response()->json(['data' => $this->present($value)]);
    }

    private function present(array $value): array
    {
        $path = $value['og_image_path'] ?? null;

        return [
            'meta_title' => $value['meta_title'] ?? ['id' => '', 'en' => ''],
            'meta_description' => $value['meta_description'] ?? ['id' => '', 'en' => ''],
            'og_image' => $path ? ['path' => $path, 'url' => $this->media->url($path), 'name' => null, 'size' => null] : null,
            'keywords' => $value['keywords'] ?? [],
        ];
    }
}
