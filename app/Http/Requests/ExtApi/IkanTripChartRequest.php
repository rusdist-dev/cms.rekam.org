<?php

namespace App\Http\Requests\ExtApi;

use App\Services\Ikan\IkanTripChartService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Query parameters for the trip chart.
 *
 * Validated rather than silently coerced: a chart built from `tipe_tanggal=bulan`
 * or `dari=01/02/2026` would render a plausible-looking but wrong picture, and
 * the caller would have no way to tell. A 422 naming the parameter is the only
 * answer that cannot be misread.
 */
class IkanTripChartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $filters = array_fill_keys(
            IkanTripChartService::FILTERS,
            ['nullable', 'string', 'max:255'],
        );

        return $filters + [
            'tipe_tanggal' => ['nullable', 'in:monthly,yearly'],
            'dari' => ['nullable', 'date_format:Y-m-d'],
            // Equal is allowed: a single day is a legitimate, if dull, range.
            'sampai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dari'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipe_tanggal.in' => 'tipe_tanggal harus monthly atau yearly.',
            'dari.date_format' => 'dari harus berformat YYYY-MM-DD.',
            'sampai.date_format' => 'sampai harus berformat YYYY-MM-DD.',
            'sampai.after_or_equal' => 'sampai tidak boleh mendahului dari.',
        ];
    }

    public function tipeTanggal(): string
    {
        return $this->query('tipe_tanggal') ?: IkanTripChartService::MONTHLY;
    }

    /** @return array<string, string> only the filters that carry a value */
    public function filters(): array
    {
        return array_filter(
            $this->only(IkanTripChartService::FILTERS),
            fn ($value) => filled($value),
        );
    }
}
