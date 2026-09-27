<?php

namespace App\Http\Requests\ExtApi;

use App\Services\Bsc\BscCatchChartService;
use Illuminate\Foundation\Http\FormRequest;

/** Query parameters for the BSC catch-composition chart. */
class BscCatchChartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $filters = array_fill_keys(BscCatchChartService::FILTERS, ['nullable', 'string', 'max:255']);

        return $filters + [
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dari'],
        ];
    }

    public function messages(): array
    {
        return [
            'dari.date_format' => 'dari harus berformat YYYY-MM-DD.',
            'sampai.date_format' => 'sampai harus berformat YYYY-MM-DD.',
            'sampai.after_or_equal' => 'sampai tidak boleh mendahului dari.',
        ];
    }

    /** @return array<string, string> only the filters that carry a value */
    public function filters(): array
    {
        return array_filter($this->only(BscCatchChartService::FILTERS), fn ($value) => filled($value));
    }
}
