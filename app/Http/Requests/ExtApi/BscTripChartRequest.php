<?php

namespace App\Http\Requests\ExtApi;

use App\Services\Bsc\BscTripChartService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Query parameters for the BSC trip chart. Validated rather than silently
 * coerced: a chart built from a bad parameter looks plausible and is wrong.
 */
class BscTripChartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $filters = array_fill_keys(BscTripChartService::FILTERS, ['nullable', 'string', 'max:255']);

        return $filters + [
            'tipe_tanggal' => ['nullable', 'in:monthly,yearly'],
            'dari' => ['nullable', 'date_format:Y-m-d'],
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
        return $this->query('tipe_tanggal') ?: BscTripChartService::MONTHLY;
    }

    /** @return array<string, string> only the filters that carry a value */
    public function filters(): array
    {
        return array_filter($this->only(BscTripChartService::FILTERS), fn ($value) => filled($value));
    }
}
