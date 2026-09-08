<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\Concerns\ValidatesTranslatable;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSiteIdentityRequest extends FormRequest
{
    use ValidatesTranslatable;

    public function authorize(): bool
    {
        return $this->user()->can('settings.update');
    }

    public function rules(): array
    {
        return array_merge($this->translatableRules(['tagline' => false]), [
            'name' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:'.implode(',', config('cms.media.image.mimes')), 'max:'.config('cms.media.image.max_kb')],
            'remove_logo' => ['boolean'],
            'favicon' => ['nullable', 'image', 'mimes:'.implode(',', config('cms.media.image.mimes')), 'max:'.config('cms.media.image.max_kb')],
            'remove_favicon' => ['boolean'],
        ]);
    }

    public function attributes(): array
    {
        return $this->translatableAttributes(['tagline' => 'tagline']) + [
            'name' => 'nama situs',
            'logo' => 'logo',
            'favicon' => 'favicon',
        ];
    }
}
