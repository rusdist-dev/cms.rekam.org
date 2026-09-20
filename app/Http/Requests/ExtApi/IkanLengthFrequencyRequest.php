<?php

namespace App\Http\Requests\ExtApi;

use App\Services\Ikan\IkanLengthFrequencyService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query parameters for the length-frequency chart.
 *
 * Validated rather than silently coerced. A histogram is read as a statement
 * about a stock, so a bad class width or an unparsed date must fail loudly
 * instead of producing a plausible picture nobody can tell is wrong.
 */
class IkanLengthFrequencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $filters = array_fill_keys(
            IkanLengthFrequencyService::FILTERS,
            ['nullable', 'string', 'max:255'],
        );

        return $filters + [
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dari'],
            'tipe_panjang' => ['nullable', Rule::in(IkanLengthFrequencyService::LENGTH_TYPES)],
            // Upper bound because measurements top out at 150: a wider class
            // than that collapses the whole distribution into one bar.
            'selang_kelas' => ['nullable', 'numeric', 'min:0.1', 'max:50'],
            'lm' => ['nullable', 'numeric', 'min:0.1', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'dari.date_format' => 'dari harus berformat YYYY-MM-DD.',
            'sampai.date_format' => 'sampai harus berformat YYYY-MM-DD.',
            'sampai.after_or_equal' => 'sampai tidak boleh mendahului dari.',
            'tipe_panjang.in' => 'tipe_panjang harus TL atau FL. Kosongkan untuk menampilkan keduanya.',
            'selang_kelas.min' => 'selang_kelas minimal 0.1.',
            'selang_kelas.max' => 'selang_kelas maksimal 50.',
        ];
    }

    /** Absent means both TL and FL — see the service's note on mixing them. */
    public function tipePanjang(): ?string
    {
        return filled($this->query('tipe_panjang')) ? $this->query('tipe_panjang') : null;
    }

    public function selangKelas(): float
    {
        return filled($this->query('selang_kelas'))
            ? (float) $this->query('selang_kelas')
            : IkanLengthFrequencyService::DEFAULT_CLASS_WIDTH;
    }

    public function lm(): ?float
    {
        return filled($this->query('lm')) ? (float) $this->query('lm') : null;
    }

    /** @return array<string, string> only the filters that carry a value */
    public function filters(): array
    {
        return array_filter(
            $this->only(IkanLengthFrequencyService::FILTERS),
            fn ($value) => filled($value),
        );
    }
}
