<?php

namespace App\Http\Requests\Partner;

class UpdatePartnerRequest extends StorePartnerRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('partners.update');
    }
}
