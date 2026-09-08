<?php

namespace App\Http\Requests\Publication;

use App\Http\Requests\Concerns\ValidatesTranslatable;
use Illuminate\Foundation\Http\FormRequest;

class StorePublicationRequest extends FormRequest
{
    use ValidatesTranslatable;

    public function authorize(): bool
    {
        return $this->user()->can('publications.create');
    }

    public function rules(): array
    {
        return array_merge(
            $this->translatableRules(['title' => true, 'description' => false]),
            [
                // A `publication_categories` taxonomy slug — free-form like
                // Event's `category`, not a hard enum (context.md §5.12).
                'category' => ['required', 'string', 'max:64'],
                'file' => array_merge($this->fileRule(), [
                    'mimes:'.implode(',', config('cms.media.document.mimes')),
                    'max:'.config('cms.media.document.max_kb'),
                ]),
                'remove_file' => ['boolean'],
                'cover' => ['nullable', 'image', 'mimes:'.implode(',', config('cms.media.image.mimes')), 'max:'.config('cms.media.image.max_kb')],
                'remove_cover' => ['boolean'],
                'is_featured' => ['boolean'],
            ],
        );
    }

    /** A publication without a document is not meaningful, so create requires one. */
    protected function fileRule(): array
    {
        return ['required', 'file'];
    }

    public function attributes(): array
    {
        return $this->translatableAttributes([
            'title' => 'judul',
            'description' => 'deskripsi',
        ]) + [
            'category' => 'kategori',
            'file' => 'berkas PDF',
            'cover' => 'gambar sampul',
            'is_featured' => 'unggulan',
        ];
    }
}
