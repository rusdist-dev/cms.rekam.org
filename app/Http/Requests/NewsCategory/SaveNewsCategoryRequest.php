<?php

namespace App\Http\Requests\NewsCategory;

use App\Http\Requests\Concerns\ValidatesTranslatable;
use Illuminate\Foundation\Http\FormRequest;

class SaveNewsCategoryRequest extends FormRequest
{
    use ValidatesTranslatable;

    public function authorize(): bool
    {
        return $this->route('category')
            ? $this->user()->can('news.update')
            : $this->user()->can('news.create');
    }

    public function rules(): array
    {
        return array_merge(
            $this->translatableRules(['name' => true, 'slug' => false], 255),
            ['sort_order' => ['nullable', 'integer', 'min:0']],
        );
    }

    public function attributes(): array
    {
        return $this->translatableAttributes([
            'name' => 'nama kategori',
            'slug' => 'slug',
        ]) + ['sort_order' => 'urutan'];
    }
}
