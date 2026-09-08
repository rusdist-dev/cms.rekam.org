<?php

namespace App\Http\Requests\Unit;

class UpdateUnitRequest extends StoreUnitRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('units.update');
    }
}
