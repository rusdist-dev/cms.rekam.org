<?php

namespace App\Http\Resources\Dash;

use App\Models\User;
use App\Support\Labels;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'label' => Labels::role($this->name),
            'users_count' => $this->users_count ?? $this->users()->count(),
            'permissions_count' => $this->permissions_count ?? $this->permissions()->count(),
            'permissions' => $this->whenLoaded(
                'permissions',
                fn () => $this->permissions->pluck('name')->all(),
                []
            ),
            // super-admin passes every gate through Gate::before, so editing its
            // permission list would be misleading — the UI locks it instead.
            'is_locked' => $this->name === User::SUPER_ADMIN,
        ];
    }
}
