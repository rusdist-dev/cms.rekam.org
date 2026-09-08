<?php

namespace App\Http\Resources\Dash;

use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description ?? ['id' => null, 'en' => null],
            'category' => $this->category,
            // {path, url, name, size} for the edit form's mediaPicker — real
            // filename/size (unlike Team/Partner's images) since the picker
            // displays them for a document.
            'file' => $this->file_path ? [
                'path' => $this->file_path,
                'url' => app(MediaService::class)->url($this->file_path),
                'name' => $this->file_name,
                'size' => $this->file_size,
            ] : null,
            'file_name' => $this->file_name,
            'cover' => $this->cover_path ? [
                'path' => $this->cover_path,
                'url' => app(MediaService::class)->url($this->cover_path),
                'name' => null,
                'size' => null,
            ] : null,
            'cover_url' => app(MediaService::class)->url($this->cover_path),
            'is_featured' => $this->is_featured,
            'sort_order' => $this->sort_order,
            'updated_at' => $this->updated_at?->format('Y-m-d H:i'),
        ];
    }
}
