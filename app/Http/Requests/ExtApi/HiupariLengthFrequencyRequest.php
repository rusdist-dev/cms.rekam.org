<?php

namespace App\Http\Requests\ExtApi;

use App\Services\Hiupari\HiupariLengthFrequencyService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query parameters for the shark and ray length histogram.
 *
 * `jenis_ukuran` is validated against the five columns upstream actually
 * records: asking for a sixth would otherwise come back as an empty chart
 * rather than as an error.
 */
class HiupariLengthFrequencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'spesies' => ['nullable', 'string', 'max:255'],
            'jenis_kelamin' => ['nullable', Rule::in(HiupariLengthFrequencyService::SEXES)],
            'jenis_ukuran' => ['nullable', Rule::in(array_keys(HiupariLengthFrequencyService::MEASUREMENTS))],
            'selang_kelas' => ['nullable', 'numeric', 'min:0.1', 'max:50'],
            'kematangan_matang' => ['nullable', 'integer', 'min:1', 'max:'.HiupariLengthFrequencyService::MAX_STAGE],
        ];
    }

    public function messages(): array
    {
        return [
            'jenis_ukuran.in' => 'jenis_ukuran harus salah satu dari: '
                .implode(', ', array_keys(HiupariLengthFrequencyService::MEASUREMENTS)).'.',
            'selang_kelas.min' => 'selang_kelas minimal 0.1.',
            'selang_kelas.max' => 'selang_kelas maksimal 50.',
            'jenis_kelamin.in' => 'jenis_kelamin harus M atau F. Kosongkan untuk menampilkan keduanya.',
            'kematangan_matang.max' => 'kematangan_matang maksimal '.HiupariLengthFrequencyService::MAX_STAGE
                .'; hulu hanya mencatat tahap 0 sampai '.HiupariLengthFrequencyService::MAX_STAGE.'.',
        ];
    }

    public function spesies(): ?string
    {
        return filled($this->query('spesies')) ? $this->query('spesies') : null;
    }

    /** Absent means both sexes — and then Lm is null, because clasper maturity is a male character. */
    public function jenisKelamin(): ?string
    {
        return filled($this->query('jenis_kelamin')) ? $this->query('jenis_kelamin') : null;
    }

    public function kematanganMatang(): int
    {
        return filled($this->query('kematangan_matang'))
            ? (int) $this->query('kematangan_matang')
            : HiupariLengthFrequencyService::DEFAULT_MATURE_STAGE;
    }

    public function jenisUkuran(): string
    {
        return filled($this->query('jenis_ukuran'))
            ? $this->query('jenis_ukuran')
            : HiupariLengthFrequencyService::DEFAULT_MEASUREMENT;
    }

    public function selangKelas(): float
    {
        return filled($this->query('selang_kelas'))
            ? (float) $this->query('selang_kelas')
            : HiupariLengthFrequencyService::DEFAULT_CLASS_WIDTH;
    }
}
