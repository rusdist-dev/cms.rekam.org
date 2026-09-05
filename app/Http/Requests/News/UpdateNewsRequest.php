<?php

namespace App\Http\Requests\News;

class UpdateNewsRequest extends StoreNewsRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('news.update');
    }
}
