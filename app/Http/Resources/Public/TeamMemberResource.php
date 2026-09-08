<?php

namespace App\Http\Resources\Public;

use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            // Not translatable — a person's name has no ID/EN version.
            'name' => $this->name,
            'position' => $this->trans('position'),
            'bio' => $this->trans('bio'),
            'photo_url' => app(MediaService::class)->url($this->photo_path),
            'email' => $this->email,
            'socials' => $this->socials ?? ['linkedin' => '', 'instagram' => ''],
        ];
    }
}
