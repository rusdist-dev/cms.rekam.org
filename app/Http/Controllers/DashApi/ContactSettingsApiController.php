<?php

namespace App\Http\Controllers\DashApi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateContactSettingsRequest;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;

/**
 * `contact_info` is a flat singleton object, not an options list, so it does
 * not belong in TaxonomyService (context.md §5.12 is about taxonomy, not
 * arbitrary settings) — it gets its own tiny read/write pair instead.
 *
 * Not tied to any one ContactMessage, so this is gated by the route's
 * `permission:contacts.view` / `permission:contacts.update` middleware alone
 * rather than a policy — a policy ability here would need a model instance
 * that does not exist for a singleton resource.
 */
class ContactSettingsApiController extends Controller
{
    private const DEFAULTS = [
        'email' => '',
        'phone' => '',
        'whatsapp' => '',
        'address' => ['id' => '', 'en' => ''],
        'map_embed' => '',
    ];

    public function show(): JsonResponse
    {
        return response()->json(['data' => SiteSetting::get('contact', 'info', self::DEFAULTS)]);
    }

    public function update(UpdateContactSettingsRequest $request): JsonResponse
    {
        $data = $request->validated();

        SiteSetting::put('contact', 'info', $data);

        return response()->json(['data' => $data]);
    }
}
