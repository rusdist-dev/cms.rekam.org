<?php

namespace App\Http\Resources\Dash;

use App\Services\MediaService;
use App\Services\TenantManager;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'location' => $this->location,
            'fee_note' => $this->fee_note,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,

            'category' => $this->category,
            'start_at' => $this->start_at?->format('Y-m-d\TH:i'),
            'end_at' => $this->end_at?->format('Y-m-d\TH:i'),
            'is_all_day' => (bool) $this->is_all_day,

            'fee' => $this->fee !== null ? (float) $this->fee : null,
            'is_free' => $this->isFree(),
            'quota' => $this->quota,
            'registration_url' => $this->registration_url,

            'cover_path' => $this->cover_path,
            'cover_url' => app(MediaService::class)->url($this->cover_path),

            'status' => $this->status,
            'translation_complete' => $this->translationCompleteness(['title', 'description']),

            // Rundown belongs to the response only where the module is on
            // (context.md §5.7) — otherwise the form would render a tab the
            // tenant does not have.
            $this->mergeWhen(
                app(TenantManager::class)->hasFeature('event_rundown'),
                fn () => [
                    // Resolved only when the relation is actually loaded: the
                    // index uses withCount and never loads the rows, and
                    // ::collection(MissingValue) blows up rather than degrading.
                    'rundowns' => $this->whenLoaded(
                        'rundowns',
                        fn () => EventRundownResource::collection($this->rundowns)->resolve(),
                    ),
                    'rundowns_count' => $this->whenCounted(
                        'rundowns',
                        fn () => $this->rundowns_count,
                        fn () => $this->relationLoaded('rundowns') ? $this->rundowns->count() : 0,
                    ),
                ]
            ),

            'is_trashed' => $this->trashed(),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i'),
        ];
    }
}
