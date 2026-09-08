<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Rules depend on which `{group}` the route resolved (`socials`/`map` — the
 * route's own `whereIn` already keeps anything else from reaching here). The
 * defaults/rules map is the single source of truth `SiteSettingsApiController`
 * reads from too, so there is one place to add a settings group, not two.
 */
class UpdateSiteSettingsRequest extends FormRequest
{
    public const GROUPS = [
        'socials' => [
            'defaults' => [
                'instagram' => '', 'linkedin' => '', 'youtube' => '',
                'facebook' => '', 'x' => '', 'tiktok' => '',
            ],
            'rules' => [
                'instagram' => ['nullable', 'url', 'max:255'],
                'linkedin' => ['nullable', 'url', 'max:255'],
                'youtube' => ['nullable', 'url', 'max:255'],
                'facebook' => ['nullable', 'url', 'max:255'],
                'x' => ['nullable', 'url', 'max:255'],
                'tiktok' => ['nullable', 'url', 'max:255'],
            ],
        ],
        'map' => [
            'defaults' => ['embed' => ''],
            'rules' => ['embed' => ['nullable', 'string']],
        ],
    ];

    public function authorize(): bool
    {
        return $this->user()->can('settings.update');
    }

    public function rules(): array
    {
        return self::GROUPS[$this->route('group')]['rules'] ?? [];
    }
}
