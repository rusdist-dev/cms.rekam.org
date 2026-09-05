<?php

namespace App\Http\Requests\Event;

use App\Http\Requests\Concerns\ValidatesTranslatable;
use App\Services\TenantManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
{
    use ValidatesTranslatable;

    public function authorize(): bool
    {
        return $this->user()->can('events.create');
    }

    public function rules(): array
    {
        $rules = array_merge(
            $this->translatableRules([
                'title' => true,
                'slug' => false,
                'description' => true,
                'location' => true,
                'fee_note' => false,
            ]),
            [
                'category' => ['nullable', 'string', 'max:64'],
                'start_at' => ['required', 'date'],
                'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
                'is_all_day' => ['boolean'],
                'fee' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
                'quota' => ['nullable', 'integer', 'min:0', 'max:1000000'],
                'registration_url' => ['nullable', 'url', 'max:255'],
                'cover' => ['nullable', 'image', 'mimes:'.implode(',', config('cms.media.image.mimes')), 'max:'.config('cms.media.image.max_kb')],
                'remove_cover' => ['boolean'],
                'status' => ['required', Rule::in(array_keys(config('cms.statuses')))],
            ],
            $this->seoRules(),
            $this->rundownRules(),
        );

        return $rules;
    }

    /**
     * Rundown rows arrive with their parent and are validated per index, so a
     * 422 can be shown on the offending row (context.md §4.11).
     */
    private function rundownRules(): array
    {
        // A tenant without the flag has no rundown tab, so any rows it sends are
        // rejected rather than silently stored (context.md §5.6).
        if (! app(TenantManager::class)->hasFeature('event_rundown')) {
            return ['rundowns' => ['prohibited']];
        }

        $rules = [
            'rundowns' => ['array', 'max:100'],
            'rundowns.*.id' => ['nullable', 'integer'],
            'rundowns.*.time' => ['nullable', 'date_format:H:i'],
            'rundowns.*.title' => ['array'],
            'rundowns.*.description' => ['array'],
        ];

        $default = config('cms.default_locale');

        foreach (config('cms.locales') as $locale) {
            $rules["rundowns.*.title.{$locale}"] = [
                $locale === $default ? 'required' : 'nullable',
                'string', 'max:255',
            ];
            $rules["rundowns.*.description.{$locale}"] = ['nullable', 'string', 'max:1000'];
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->requireEnglishWhenPublishing($validator, 'title');
        });
    }

    public function messages(): array
    {
        return [
            'rundowns.prohibited' => 'Modul rundown tidak aktif untuk company ini.',
            'rundowns.*.time.date_format' => 'Jam sesi harus dalam format HH:MM.',
            'end_at.after_or_equal' => 'Waktu selesai tidak boleh sebelum waktu mulai.',
        ];
    }

    public function attributes(): array
    {
        return $this->translatableAttributes([
            'title' => 'judul',
            'slug' => 'slug',
            'description' => 'deskripsi',
            'location' => 'lokasi',
            'fee_note' => 'keterangan biaya',
            'meta_title' => 'meta title',
            'meta_description' => 'meta description',
        ]) + [
            'category' => 'kategori',
            'start_at' => 'waktu mulai',
            'end_at' => 'waktu selesai',
            'fee' => 'biaya',
            'quota' => 'kuota',
            'registration_url' => 'tautan pendaftaran',
            'cover' => 'gambar sampul',
            'status' => 'status',
        ];
    }
}
