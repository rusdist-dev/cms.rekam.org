<?php

namespace App\Http\Resources\Public;

use App\Services\MediaService;
use App\Services\TenantManager;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NewsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->trans('slug'),
            'title' => $this->trans('title'),
            'excerpt' => $this->trans('excerpt'),
            'body' => $this->trans('body'),
            'meta_title' => $this->trans('meta_title'),
            'meta_description' => $this->trans('meta_description'),
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'slug' => $this->category->trans('slug'),
                'name' => $this->category->trans('name'),
            ] : null),
            $this->mergeWhen(
                app(TenantManager::class)->hasFeature('news_programs'),
                fn () => ['related_programs' => $this->related_programs ?? []],
            ),
            'cover_url' => app(MediaService::class)->url($this->cover_path),
            'published_at' => $this->published_at?->toIso8601String(),
            'author_name' => $this->author_name,
        ];
    }
}
