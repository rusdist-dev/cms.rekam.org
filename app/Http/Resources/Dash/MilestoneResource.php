<?php

namespace App\Http\Resources\Dash;

use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MilestoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body ?? ['id' => null, 'en' => null],
            // {path, url, name, size} for the edit form's mediaPicker.
            'cover' => $this->cover_path ? [
                'path' => $this->cover_path,
                'url' => app(MediaService::class)->url($this->cover_path),
                'name' => null,
                'size' => null,
            ] : null,
            // Flat convenience field: the index timeline's row partial reads
            // this directly, same as News/Event's cover_url.
            'cover_url' => app(MediaService::class)->url($this->cover_path),
            'year' => $this->year,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'updated_at' => $this->updated_at?->format('Y-m-d H:i'),
        ];
    }
}
