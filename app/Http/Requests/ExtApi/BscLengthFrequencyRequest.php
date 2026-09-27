<?php

namespace App\Http\Requests\ExtApi;

use App\Services\Bsc\BscFilterService;
use App\Services\Bsc\BscLengthFrequencyService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query parameters for the BSC carapace-width histogram.
 *
 * `tkg_matang` is validated against the stages upstream actually records:
 * asking for maturity at stage 5 on a three-point scale would silently return
 * an Lm of null rather than an error.
 */
class BscLengthFrequencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $filters = array_fill_keys(BscLengthFrequencyService::FILTERS, ['nullable', 'string', 'max:255']);

        return $filters + [
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dari'],
            'jenis_kelamin' => ['nullable', Rule::in(BscFilterService::SEXES)],
            'selang_kelas' => ['nullable', 'numeric', 'min:0.1', 'max:50'],
            'tkg_matang' => ['nullable', 'integer', 'min:1', 'max:3'],
        ];
    }

    public function messages(): array
    {
        return [
            'dari.date_format' => 'dari harus berformat YYYY-MM-DD.',
            'sampai.date_format' => 'sampai harus berformat YYYY-MM-DD.',
            'sampai.after_or_equal' => 'sampai tidak boleh mendahului dari.',
            'jenis_kelamin.in' => 'jenis_kelamin harus JANTAN atau BETINA. Kosongkan untuk menampilkan keduanya.',
            'tkg_matang.max' => 'tkg_matang maksimal 3; hulu hanya mencatat TKG 1, 2, dan 3.',
        ];
    }

    /** Absent means both sexes, plus the rows whose sex could not be read. */
    public function jenisKelamin(): ?string
    {
        return filled($this->query('jenis_kelamin')) ? $this->query('jenis_kelamin') : null;
    }

    public function selangKelas(): float
    {
        return filled($this->query('selang_kelas'))
            ? (float) $this->query('selang_kelas')
            : BscLengthFrequencyService::DEFAULT_CLASS_WIDTH;
    }

    public function tkgMatang(): int
    {
        return filled($this->query('tkg_matang'))
            ? (int) $this->query('tkg_matang')
            : BscLengthFrequencyService::DEFAULT_MATURE_STAGE;
    }

    /** @return array<string, string> only the filters that carry a value */
    public function filters(): array
    {
        return array_filter($this->only(BscLengthFrequencyService::FILTERS), fn ($value) => filled($value));
    }
}
