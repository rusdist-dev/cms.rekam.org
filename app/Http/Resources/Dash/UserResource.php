<?php

namespace App\Http\Resources\Dash;

use App\Support\Labels;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Returning the model directly is forbidden (context.md §4.4) — and here it
 * would also leak the password hash and remember token.
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $role = $this->roles->first();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar_url' => $this->avatar,
            'role' => $role?->name,
            'role_label' => Labels::role($role?->name),
            'tenants' => $this->whenLoaded(
                'tenants',
                fn () => $this->tenants->pluck('slug')->all(),
                []
            ),
            'is_active' => (bool) $this->is_active,
            'last_login_at' => $this->last_login_at?->format('Y-m-d H:i'),
            'created_at' => $this->created_at?->format('Y-m-d H:i'),
        ];
    }
}
