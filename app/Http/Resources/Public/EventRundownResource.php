<?php

namespace App\Http\Resources\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventRundownResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'time' => $this->time ? substr((string) $this->time, 0, 5) : null,
            'title' => $this->trans('title'),
            'description' => $this->trans('description'),
        ];
    }
}
