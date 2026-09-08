<?php

namespace App\Http\Controllers\DashApi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSiteSettingsRequest;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;

/**
 * The plain-field singleton settings groups — no file uploads, so a single GET
 * (JSON) / PUT (JSON) pair covers both, unlike identity/seo which need
 * multipart POST for their image fields.
 */
class SiteSettingsApiController extends Controller
{
    public function show(string $group): JsonResponse
    {
        abort_unless(isset(UpdateSiteSettingsRequest::GROUPS[$group]), 404, 'Pengaturan tidak ditemukan.');

        return response()->json(['data' => SiteSetting::get('site', $group, UpdateSiteSettingsRequest::GROUPS[$group]['defaults'])]);
    }

    public function update(UpdateSiteSettingsRequest $request, string $group): JsonResponse
    {
        $data = $request->validated();

        SiteSetting::put('site', $group, $data);

        return response()->json(['data' => $data]);
    }
}
