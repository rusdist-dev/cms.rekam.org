<?php

namespace App\Http\Resources\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventBenefitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'title' => $this->trans('title'),
        ];
    }
}
