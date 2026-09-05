<?php

namespace App\Http\Requests\News;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkNewsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $action = $this->input('action');

        // Publishing is a separate right from editing, so a bulk publish needs
        // it just as a single one does.
        if ($action === 'publish') {
            return $this->user()->can('news.update') && $this->user()->can('news.publish');
        }

        return $action === 'delete'
            ? $this->user()->can('news.delete')
            : $this->user()->can('news.update');
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['publish', 'draft', 'delete'])],
            // Capped so one request cannot rewrite the whole table.
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer', Rule::exists('tenant.news', 'id')],
        ];
    }

    public function attributes(): array
    {
        return ['action' => 'aksi', 'ids' => 'berita terpilih'];
    }
}
