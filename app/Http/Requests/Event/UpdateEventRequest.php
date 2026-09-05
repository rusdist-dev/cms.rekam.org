<?php

namespace App\Http\Requests\Event;

class UpdateEventRequest extends StoreEventRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('events.update');
    }
}
