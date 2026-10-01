<?php

namespace App\Http\Resources\Dash;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventBenefitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'sort_order' => $this->sort_order,
        ];
    }
}
