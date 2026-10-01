<?php

namespace App\Http\Resources\Public;

use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PartnerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'title' => $this->trans('title'),
            'category' => $this->category,
            'category_label' => $this->category ? (config('cms.partner_categories')[$this->category] ?? $this->category) : null,
            'logo_url' => app(MediaService::class)->url($this->logo_path),
            'url' => $this->url,
        ];
    }
}
