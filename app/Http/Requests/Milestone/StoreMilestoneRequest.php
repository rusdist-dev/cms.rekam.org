<?php

namespace App\Http\Requests\Milestone;

use App\Http\Requests\Concerns\ValidatesTranslatable;
use Illuminate\Foundation\Http\FormRequest;

class StoreMilestoneRequest extends FormRequest
{
    use ValidatesTranslatable;

    public function authorize(): bool
    {
        return $this->user()->can('milestones.create');
    }

    public function rules(): array
    {
        return array_merge(
            $this->translatableRules(['title' => true, 'body' => false]),
            [
                // Drives the timeline position — required so the item always
                // has a real place on the linimasa (plan.md §5.5 q5).
                'year' => ['required', 'integer', 'min:1900', 'max:2200'],
                'cover' => ['nullable', 'image', 'mimes:'.implode(',', config('cms.media.image.mimes')), 'max:'.config('cms.media.image.max_kb')],
                'remove_cover' => ['boolean'],
                'is_active' => ['boolean'],
            ],
        );
    }

    public function attributes(): array
    {
        return $this->translatableAttributes([
            'title' => 'judul',
            'body' => 'isi',
        ]) + [
            'year' => 'tahun',
            'cover' => 'gambar',
            'is_active' => 'status tampil',
        ];
    }
}
