<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\Concerns\ValidatesTranslatable;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSiteSeoRequest extends FormRequest
{
    use ValidatesTranslatable;

    public function authorize(): bool
    {
        return $this->user()->can('settings.update');
    }

    public function rules(): array
    {
        return array_merge($this->seoRules(), [
            'keywords' => ['array'],
            'keywords.*' => ['string', 'max:64'],
            'og_image' => ['nullable', 'image', 'mimes:'.implode(',', config('cms.media.image.mimes')), 'max:'.config('cms.media.image.max_kb')],
            'remove_og_image' => ['boolean'],
        ]);
    }

    public function attributes(): array
    {
        return $this->translatableAttributes([
            'meta_title' => 'judul meta',
            'meta_description' => 'deskripsi meta',
        ]) + [
            'keywords' => 'kata kunci',
            'og_image' => 'gambar og',
        ];
    }
}
