<?php

namespace App\Http\Requests\ExtApi;

use App\Services\JogoLaut\JogoLautMonitoringService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Query parameters for the JOGO LAUT monitoring payload. Validated rather than
 * coerced: `days` bounds a query and `window` shapes every tide figure, and a
 * payload built from a silently corrected parameter looks right and is not
 * what was asked for.
 */
class JogoLautMonitoringRequest extends FormRequest
{
    public const LOCALES = ['id', 'en'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sections = array_keys(JogoLautMonitoringService::SECTIONS);

        return [
            'include' => ['nullable', 'string', 'max:255', function (string $attribute, mixed $value, Closure $fail) use ($sections) {
                $unknown = array_diff($this->split((string) $value), $sections);

                if ($unknown !== []) {
                    $fail('include memuat section yang tidak dikenal: '.implode(', ', $unknown).'. Pilihan: '.implode(', ', $sections).'.');
                }
            }],
            'days' => ['nullable', 'integer', 'min:1', 'max:'.config('jogolaut.max_interval_days')],
            'window' => ['nullable', 'integer', 'min:3', 'max:99', function (string $attribute, mixed $value, Closure $fail) {
                if ((int) $value % 2 === 0) {
                    $fail('window harus bilangan ganjil agar rata-rata bergeraknya terpusat.');
                }
            }],
            // 30 days of 5-minute readings is 8,640 rows: even at limit=1 no
            // real page lies past this, and an unbounded page overflows the
            // offset arithmetic.
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'locale' => ['nullable', 'in:'.implode(',', self::LOCALES)],
        ];
    }

    public function messages(): array
    {
        return [
            'days.integer' => 'days harus bilangan bulat.',
            'days.min' => 'days minimal 1.',
            'days.max' => 'days maksimal :max.',
            'window.integer' => 'window harus bilangan bulat.',
            'window.min' => 'window minimal 3.',
            'window.max' => 'window maksimal 99.',
            'page.max' => 'page maksimal :max.',
            'locale.in' => 'locale harus id atau en.',
        ];
    }

    /**
     * Normalised parameters. `include` is sorted into response order and
     * `page`/`limit` are dropped unless the table is requested, so requests
     * that would build the same payload share one cache entry.
     *
     * @return array{include: array<int, string>, days: int, window: int, page: int, limit: int, locale: string}
     */
    public function params(): array
    {
        $defaults = JogoLautMonitoringService::defaults($this->query('locale') ?: 'id');
        $requested = $this->split((string) $this->query('include', ''));
        $include = $requested === []
            ? $defaults['include']
            : array_values(array_intersect($defaults['include'], $requested));

        $withTable = in_array('table', $include, true);

        return [
            'include' => $include,
            'days' => (int) ($this->query('days') ?: $defaults['days']),
            'window' => (int) ($this->query('window') ?: $defaults['window']),
            'page' => $withTable ? (int) ($this->query('page') ?: $defaults['page']) : $defaults['page'],
            'limit' => $withTable ? (int) ($this->query('limit') ?: $defaults['limit']) : $defaults['limit'],
            'locale' => $defaults['locale'],
        ];
    }

    /** @return array<int, string> */
    private function split(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', strtolower($value)))));
    }
}
