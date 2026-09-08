<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\Concerns\ValidatesTranslatable;
use Illuminate\Foundation\Http\FormRequest;

class UpdateContactSettingsRequest extends FormRequest
{
    use ValidatesTranslatable;

    public function authorize(): bool
    {
        return $this->user()->can('contacts.update');
    }

    public function rules(): array
    {
        return array_merge($this->translatableRules(['address' => true]), [
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'whatsapp' => ['nullable', 'string', 'max:32'],
            'map_embed' => ['nullable', 'string'],
        ]);
    }

    public function attributes(): array
    {
        return $this->translatableAttributes(['address' => 'alamat']) + [
            'email' => 'email',
            'phone' => 'telepon',
            'whatsapp' => 'whatsapp',
            'map_embed' => 'embed peta',
        ];
    }
}
