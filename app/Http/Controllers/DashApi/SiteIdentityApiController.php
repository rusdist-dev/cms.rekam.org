<?php

namespace App\Http\Controllers\DashApi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSiteIdentityRequest;
use App\Models\SiteSetting;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;

/**
 * Site identity (name, tagline, logo, favicon) — a singleton per tenant, not a
 * TaxonomyService option list (context.md §5.12 is about taxonomy). Gated by
 * the route's `permission:settings.view`/`permission:settings.update`
 * middleware rather than a policy, same reasoning as ContactSettingsApiController.
 */
class SiteIdentityApiController extends Controller
{
    public function __construct(private readonly MediaService $media) {}

    public function show(): JsonResponse
    {
        $value = SiteSetting::get('site', 'identity', [
            'name' => '',
            'tagline' => ['id' => '', 'en' => ''],
            'logo_path' => null,
            'favicon_path' => null,
        ]);

        return response()->json(['data' => $this->present($value)]);
    }

    public function update(UpdateSiteIdentityRequest $request): JsonResponse
    {
        $data = $request->validated();

        $current = SiteSetting::get('site', 'identity', []);
        $logoPath = $current['logo_path'] ?? null;
        $faviconPath = $current['favicon_path'] ?? null;

        if ($request->file('logo')) {
            $logoPath = $this->swap($logoPath, $request->file('logo'));
        } elseif ($request->boolean('remove_logo')) {
            $logoPath = $this->clear($logoPath);
        }

        if ($request->file('favicon')) {
            $faviconPath = $this->swap($faviconPath, $request->file('favicon'));
        } elseif ($request->boolean('remove_favicon')) {
            $faviconPath = $this->clear($faviconPath);
        }

        $value = [
            'name' => $data['name'],
            'tagline' => $data['tagline'] ?? ['id' => null, 'en' => null],
            'logo_path' => $logoPath,
            'favicon_path' => $faviconPath,
        ];

        SiteSetting::put('site', 'identity', $value);

        return response()->json(['data' => $this->present($value)]);
    }

    /** Uploads the replacement, then deletes the previous file once the new one is stored. */
    private function swap(?string $previous, $file): string
    {
        $path = $this->media->storeImage($file, 'identity');

        if ($previous) {
            $this->media->delete($previous);
        }

        return $path;
    }

    private function clear(?string $previous): ?string
    {
        if ($previous) {
            $this->media->delete($previous);
        }

        return null;
    }

    private function present(array $value): array
    {
        return [
            'name' => $value['name'] ?? '',
            'tagline' => $value['tagline'] ?? ['id' => '', 'en' => ''],
            'logo' => $this->mediaShape($value['logo_path'] ?? null),
            'favicon' => $this->mediaShape($value['favicon_path'] ?? null),
        ];
    }

    private function mediaShape(?string $path): ?array
    {
        return $path ? ['path' => $path, 'url' => $this->media->url($path), 'name' => null, 'size' => null] : null;
    }
}
