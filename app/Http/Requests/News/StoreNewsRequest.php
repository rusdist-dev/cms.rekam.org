<?php

namespace App\Http\Requests\News;

use App\Http\Requests\Concerns\ValidatesTranslatable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNewsRequest extends FormRequest
{
    use ValidatesTranslatable;

    public function authorize(): bool
    {
        return $this->user()->can('news.create');
    }

    public function rules(): array
    {
        return array_merge(
            $this->translatableRules(['title' => true, 'slug' => false, 'excerpt' => false, 'body' => true]),
            [
                // "tenant." matters: an exists rule defaults to the central
                // connection, where this table does not exist (context.md §5.1).
                'category_id' => ['nullable', 'integer', Rule::exists('tenant.news_categories', 'id')],
                'related_programs' => ['array'],
                'related_programs.*' => ['string', 'max:64'],
                'cover' => ['nullable', 'image', 'mimes:'.implode(',', config('cms.media.image.mimes')), 'max:'.config('cms.media.image.max_kb')],
                'remove_cover' => ['boolean'],
                'status' => ['required', Rule::in(array_keys(config('cms.statuses')))],
                'published_at' => ['nullable', 'date'],
                'author_name' => ['nullable', 'string', 'max:255'],
            ],
            $this->seoRules(),
        );
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // A scheduled article without a time would never publish, and one
            // scheduled in the past is really just "publish now" mistyped.
            if ($this->input('status') === 'scheduled') {
                $published = $this->input('published_at');

                if (! $published) {
                    $validator->errors()->add('published_at', 'Jadwal terbit wajib diisi untuk status terjadwal.');
                } elseif (strtotime($published) <= time()) {
                    $validator->errors()->add('published_at', 'Jadwal terbit harus di masa depan.');
                }
            }

            $this->requireEnglishWhenPublishing($validator, 'title');
        });
    }

    public function attributes(): array
    {
        return $this->translatableAttributes([
            'title' => 'judul',
            'slug' => 'slug',
            'excerpt' => 'ringkasan',
            'body' => 'isi berita',
            'meta_title' => 'meta title',
            'meta_description' => 'meta description',
        ]) + [
            'category_id' => 'kategori',
            'related_programs' => 'program terkait',
            'cover' => 'gambar sampul',
            'status' => 'status',
            'published_at' => 'jadwal terbit',
            'author_name' => 'penulis',
        ];
    }
}
