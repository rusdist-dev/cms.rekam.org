<?php

namespace App\Http\Resources\Dash;

use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'position' => $this->position,
            'bio' => $this->bio,
            // {path, url, name, size} — the shape resources/js/alpine/mediaPicker.js
            // binds to directly, since the edit form uses plain resourceForm()
            // rather than News/Event's contentForm() cover_path/cover_url split.
            'photo' => $this->photo_path ? [
                'path' => $this->photo_path,
                'url' => app(MediaService::class)->url($this->photo_path),
                'name' => null,
                'size' => null,
            ] : null,
            // Flat convenience field: the index board's member-card partial
            // reads this directly, same as News/Event's cover_url.
            'photo_url' => app(MediaService::class)->url($this->photo_path),
            'group' => $this->group,
            'email' => $this->email,
            'socials' => $this->socials ?? ['linkedin' => '', 'instagram' => ''],
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'updated_at' => $this->updated_at?->format('Y-m-d H:i'),
        ];
    }
}
