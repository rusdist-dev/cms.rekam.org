<?php

namespace App\Http\Requests\ExtApi;

use App\Services\Stsc\StscFilterService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query parameters for the STSC production chart.
 *
 * `komoditas` is a free string rather than an enumeration: the eleven values
 * upstream uses are its own vocabulary, not a standard, and one of them
 * ("Rajungan-Kepiting") already overlaps two others — pinning the list here
 * would turn an upstream rename into a 422 for data that still exists.
 */
class StscProduksiChartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sampai = ['nullable', 'integer', 'min:1900', 'max:2100'];

        // `gte` is added only when there is something to compare against:
        // against a missing `dari_tahun` it compares a year to null and fails,
        // so an open-ended `?sampai_tahun=2021` would be rejected for naming
        // one bound instead of two.
        if ($this->filled('dari_tahun')) {
            $sampai[] = 'gte:dari_tahun';
        }

        return [
            'wpp' => ['nullable', Rule::in(StscFilterService::WPPNRI)],
            'komoditas' => ['nullable', 'string', 'max:30'],
            'dari_tahun' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'sampai_tahun' => $sampai,
        ];
    }

    public function messages(): array
    {
        return [
            'wpp.in' => 'wpp harus salah satu dari: '.implode(', ', StscFilterService::WPPNRI)
                .'. Kosongkan untuk menampilkan semua WPPNRI.',
            'sampai_tahun.gte' => 'sampai_tahun tidak boleh mendahului dari_tahun.',
        ];
    }

    public function wpp(): ?string
    {
        return filled($this->query('wpp')) ? (string) $this->query('wpp') : null;
    }

    public function komoditas(): ?string
    {
        return filled($this->query('komoditas')) ? (string) $this->query('komoditas') : null;
    }

    public function dariTahun(): ?int
    {
        return filled($this->query('dari_tahun')) ? (int) $this->query('dari_tahun') : null;
    }

    public function sampaiTahun(): ?int
    {
        return filled($this->query('sampai_tahun')) ? (int) $this->query('sampai_tahun') : null;
    }
}
