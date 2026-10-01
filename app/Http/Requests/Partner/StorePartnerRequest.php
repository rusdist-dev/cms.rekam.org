<?php

namespace App\Http\Requests\Partner;

use App\Http\Requests\Concerns\ValidatesTranslatable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePartnerRequest extends FormRequest
{
    use ValidatesTranslatable;

    public function authorize(): bool
    {
        return $this->user()->can('partners.create');
    }

    public function rules(): array
    {
        return array_merge(
            $this->translatableRules(['title' => false]),
            [
                'name' => ['required', 'string', 'max:255'],
                'category' => ['nullable', Rule::in(array_keys(config('cms.partner_categories')))],
                'url' => ['nullable', 'url', 'max:255'],
                'logo' => ['nullable', 'image', 'mimes:'.implode(',', config('cms.media.image.mimes')), 'max:'.config('cms.media.image.max_kb')],
                'remove_logo' => ['boolean'],
                'is_active' => ['boolean'],
            ],
        );
    }

    public function attributes(): array
    {
        return $this->translatableAttributes(['title' => 'keterangan']) + [
            'name' => 'nama partner',
            'category' => 'kategori',
            'url' => 'tautan situs',
            'logo' => 'logo',
            'is_active' => 'status tampil',
        ];
    }
}
