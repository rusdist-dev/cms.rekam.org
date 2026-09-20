<?php

namespace App\Http\Requests\ExtApi;

use App\Services\Ikan\IkanCatchChartService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Query parameters for the catch-per-species chart.
 *
 * Validated rather than silently coerced, for the same reason as the trip
 * chart: a chart built from `dari=01/02/2026` would render a plausible-looking
 * but wrong picture, and the caller would have no way to tell.
 */
class IkanCatchChartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $filters = array_fill_keys(
            IkanCatchChartService::FILTERS,
            ['nullable', 'string', 'max:255'],
        );

        return $filters + [
            'dari' => ['nullable', 'date_format:Y-m-d'],
            // Equal is allowed: a single day is a legitimate, if dull, range.
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
        return array_filter(
            $this->only(IkanCatchChartService::FILTERS),
            fn ($value) => filled($value),
        );
    }
}
