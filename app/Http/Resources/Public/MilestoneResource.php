<?php

namespace App\Http\Resources\Public;

use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MilestoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->trans('title'),
            'body' => $this->trans('body'),
            'cover_url' => app(MediaService::class)->url($this->cover_path),
            'year' => $this->year,
        ];
    }
}
