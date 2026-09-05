<?php

namespace App\Http\Resources\Dash;

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
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'body' => $this->body,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,

            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category?->id,
                'name' => $this->category?->name,
            ]),

            // Programmes are only meaningful where the module is on
            // (context.md §5.7).
            // Placed as a plain element, not spread: mergeWhen returns a
            // MergeValue that the resource pipeline unwraps itself.
            $this->mergeWhen(
                app(TenantManager::class)->hasFeature('news_programs'),
                fn () => ['related_programs' => $this->related_programs ?? []]
            ),

            'cover_path' => $this->cover_path,
            'cover_url' => app(MediaService::class)->url($this->cover_path),

            'status' => $this->status,
            'published_at' => $this->published_at?->format('Y-m-d H:i'),
            'author_name' => $this->author_name,
            // A freshly created row has not read back the column default, so
            // the cast keeps the field a number rather than null.
            'views' => (int) $this->views,

            // Drives the "EN kosong" hint on the index (plan.md §7).
            'translation_complete' => $this->translationCompleteness(['title', 'body']),

            'is_trashed' => $this->trashed(),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i'),
        ];
    }
}
