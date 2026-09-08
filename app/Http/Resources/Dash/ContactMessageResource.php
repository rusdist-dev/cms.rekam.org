<?php

namespace App\Http\Resources\Dash;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'subject' => $this->subject,
            'message' => $this->message,
            'status' => $this->status,
            'ip' => $this->ip,
            'created_at' => $this->created_at?->format('Y-m-d H:i'),
        ];
    }
}
