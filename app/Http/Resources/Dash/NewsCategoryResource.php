<?php

namespace App\Http\Resources\Dash;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NewsCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // The dashboard edits both locales, so the map is kept whole here —
            // flattening happens only in the public API (plan.md §2.3).
            'name' => $this->name,
            'slug' => $this->slug,
            'sort_order' => $this->sort_order,
            'news_count' => $this->whenCounted('news'),
        ];
    }
}
