<?php

namespace App\Http\Requests\Unit;

use App\Http\Requests\Concerns\ValidatesTranslatable;
use Illuminate\Foundation\Http\FormRequest;

class StoreUnitRequest extends FormRequest
{
    use ValidatesTranslatable;

    public function authorize(): bool
    {
        return $this->user()->can('units.create');
    }

    public function rules(): array
    {
        return array_merge(
            $this->translatableRules(['description' => false]),
            [
                'name' => ['required', 'string', 'max:255'],
                'domain' => ['required', 'string', 'max:255'],
                // May differ from the domain shown (plan.md §5.5 q6).
                'url' => ['required', 'url', 'max:255'],
                'logo' => ['nullable', 'image', 'mimes:'.implode(',', config('cms.media.image.mimes')), 'max:'.config('cms.media.image.max_kb')],
                'remove_logo' => ['boolean'],
                'is_active' => ['boolean'],
            ],
        );
    }

    public function attributes(): array
    {
        return $this->translatableAttributes(['description' => 'deskripsi']) + [
            'name' => 'nama unit',
            'domain' => 'domain',
            'url' => 'tautan tujuan',
            'logo' => 'logo',
            'is_active' => 'status tampil',
        ];
    }
}
