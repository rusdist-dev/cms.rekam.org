<?php

namespace App\Http\Resources\Public;

use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->trans('title'),
            'description' => $this->trans('description'),
            'category' => $this->category,
            'file_url' => app(MediaService::class)->url($this->file_path),
            'file_name' => $this->file_name,
            'file_size' => $this->file_size,
            'cover_url' => app(MediaService::class)->url($this->cover_path),
            'is_featured' => $this->is_featured,
        ];
    }
}
