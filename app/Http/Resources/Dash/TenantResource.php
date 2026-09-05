<?php

namespace App\Http\Resources\Dash;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'domain' => $this->domain,
            // db_name is shown read-only so an operator can confirm which
            // database they are looking at; it is never editable (context.md §5.2).
            'db_name' => $this->db_name,
            'features' => $this->featureMap(),
            'is_active' => (bool) $this->is_active,
            'has_api_key' => $this->api_key !== null,
            'api_key_generated_at' => $this->api_key_generated_at?->format('Y-m-d H:i'),
        ];
    }
}
