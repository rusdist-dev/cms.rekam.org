<?php

namespace App\Http\Resources\Public;

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
            'slug' => $this->trans('slug'),
            'title' => $this->trans('title'),
            'description' => $this->trans('description'),
            'location' => $this->trans('location'),
            'meta_title' => $this->trans('meta_title'),
            'meta_description' => $this->trans('meta_description'),
            'category' => $this->category,
            'start_at' => $this->start_at?->toIso8601String(),
            'end_at' => $this->end_at?->toIso8601String(),
            'is_all_day' => $this->is_all_day,
            'fee' => $this->fee,
            'fee_note' => $this->trans('fee_note'),
            'is_free' => $this->fee === null || (float) $this->fee === 0.0,
            'quota' => $this->quota,
            'registration_url' => $this->registration_url,
            'cover_url' => app(MediaService::class)->url($this->cover_path),
            $this->mergeWhen(
                app(TenantManager::class)->hasFeature('event_rundown') && $this->relationLoaded('rundowns'),
                fn () => ['rundowns' => EventRundownResource::collection($this->rundowns)->resolve()],
            ),
        ];
    }
}
