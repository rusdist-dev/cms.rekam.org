<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PublicApi\Concerns\PaginatesPublicJson;
use App\Models\Concerns\HasTranslations;
use App\Models\SiteSetting;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * One merged read of every Fase-5 settings group plus Fase-4's contact info —
 * the compro site's header/footer/contact-page bootstrap in a single request
 * (plan.md Fase 6). No feature gate: site identity is core, not a toggleable
 * module.
 */
class SettingsController extends Controller
{
    use PaginatesPublicJson;

    public function __construct(private readonly MediaService $media) {}

    public function index(Request $request): JsonResponse
    {
        return $this->dataResponse($request, 'settings', function () {
            $identity = SiteSetting::get('site', 'identity', []);
            $seo = SiteSetting::get('site', 'seo', []);
            $socials = SiteSetting::get('site', 'socials', []);
            $map = SiteSetting::get('site', 'map', []);
            $contact = SiteSetting::get('contact', 'info', []);

            return [
                'name' => $identity['name'] ?? '',
                'tagline' => HasTranslations::flatten($identity['tagline'] ?? null),
                'logo_url' => $this->media->url($identity['logo_path'] ?? null),
                'favicon_url' => $this->media->url($identity['favicon_path'] ?? null),
                'meta_title' => HasTranslations::flatten($seo['meta_title'] ?? null),
                'meta_description' => HasTranslations::flatten($seo['meta_description'] ?? null),
                'og_image_url' => $this->media->url($seo['og_image_path'] ?? null),
                'keywords' => $seo['keywords'] ?? [],
                'socials' => [
                    'instagram' => $socials['instagram'] ?? null,
                    'linkedin' => $socials['linkedin'] ?? null,
                    'youtube' => $socials['youtube'] ?? null,
                    'facebook' => $socials['facebook'] ?? null,
                    'x' => $socials['x'] ?? null,
                    'tiktok' => $socials['tiktok'] ?? null,
                ],
                'map_embed' => $map['embed'] ?? null,
                'contact' => [
                    'email' => $contact['email'] ?? null,
                    'phone' => $contact['phone'] ?? null,
                    'whatsapp' => $contact['whatsapp'] ?? null,
                    'address' => HasTranslations::flatten($contact['address'] ?? null),
                    'map_embed' => $contact['map_embed'] ?? null,
                ],
            ];
        });
    }
}
