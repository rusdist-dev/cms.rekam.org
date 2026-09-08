<?php

namespace App\Http\Resources\Dash;

use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description ?? ['id' => null, 'en' => null],
            'url' => $this->url,
            'domain' => $this->domain,
            // {path, url, name, size} for the edit form's mediaPicker.
            'logo' => $this->logo_path ? [
                'path' => $this->logo_path,
                'url' => app(MediaService::class)->url($this->logo_path),
                'name' => null,
                'size' => null,
            ] : null,
            // Flat convenience field: the index grid's card partial reads
            // this directly, same as Team/Partner's photo_url/logo_url.
            'logo_url' => app(MediaService::class)->url($this->logo_path),
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'updated_at' => $this->updated_at?->format('Y-m-d H:i'),
        ];
    }
}
