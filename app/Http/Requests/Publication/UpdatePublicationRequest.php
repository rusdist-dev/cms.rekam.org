<?php

namespace App\Http\Requests\Publication;

class UpdatePublicationRequest extends StorePublicationRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('publications.update');
    }

    /** The existing document is kept unless a replacement is uploaded. */
    protected function fileRule(): array
    {
        return ['nullable', 'file'];
    }
}
