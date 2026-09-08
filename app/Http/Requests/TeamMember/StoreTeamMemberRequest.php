<?php

namespace App\Http\Requests\TeamMember;

use App\Http\Requests\Concerns\ValidatesTranslatable;
use Illuminate\Foundation\Http\FormRequest;

class StoreTeamMemberRequest extends FormRequest
{
    use ValidatesTranslatable;

    public function authorize(): bool
    {
        return $this->user()->can('team.create');
    }

    public function rules(): array
    {
        return array_merge(
            $this->translatableRules(['position' => true, 'bio' => false]),
            [
                'name' => ['required', 'string', 'max:255'],
                // A `team_levels` taxonomy slug — free-form like Event's
                // `category`, not a hard enum: the option list is per-tenant
                // data, not code (context.md §5.12).
                'group' => ['required', 'string', 'max:64'],
                'email' => ['nullable', 'email', 'max:255'],
                'socials' => ['array'],
                'socials.linkedin' => ['nullable', 'url', 'max:255'],
                'socials.instagram' => ['nullable', 'url', 'max:255'],
                'photo' => ['nullable', 'image', 'mimes:'.implode(',', config('cms.media.image.mimes')), 'max:'.config('cms.media.image.max_kb')],
                'remove_photo' => ['boolean'],
                'is_active' => ['boolean'],
            ],
        );
    }

    public function attributes(): array
    {
        return $this->translatableAttributes([
            'position' => 'jabatan',
            'bio' => 'profil singkat',
        ]) + [
            'name' => 'nama',
            'group' => 'level',
            'email' => 'email',
            'socials.linkedin' => 'LinkedIn',
            'socials.instagram' => 'Instagram',
            'photo' => 'foto',
            'is_active' => 'status tampil',
        ];
    }
}
