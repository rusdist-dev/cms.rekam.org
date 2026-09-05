<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\Validator;

/**
 * Rules for translatable fields (context.md §6.3-§6.4).
 *
 * The asymmetry is deliberate and load-bearing: Indonesian is required, English
 * is optional. A draft may be Indonesian-only; the English site simply falls
 * back until someone translates it.
 */
trait ValidatesTranslatable
{
    /**
     * @param  array<string, bool>  $fields  field => required in the default locale
     */
    protected function translatableRules(array $fields, int $max = 65535): array
    {
        $rules = [];
        $default = config('cms.default_locale');

        foreach ($fields as $field => $requiredInDefault) {
            $rules[$field] = ['array'];

            foreach (config('cms.locales') as $locale) {
                $rules["{$field}.{$locale}"] = [
                    $locale === $default && $requiredInDefault ? 'required' : 'nullable',
                    'string',
                    "max:{$max}",
                ];
            }
        }

        return $rules;
    }

    protected function seoRules(): array
    {
        return $this->translatableRules([
            'meta_title' => false,
            'meta_description' => false,
        ], 500);
    }

    /**
     * Publishing with a half-finished English version is allowed, but an English
     * title that is present-but-blank is not — it would render an empty heading
     * on the compro rather than falling back (plan.md §2.3).
     */
    protected function requireEnglishWhenPublishing(Validator $validator, string $field): void
    {
        if ($this->input('status') !== 'published') {
            return;
        }

        $value = $this->input($field, []);

        foreach (config('cms.locales') as $locale) {
            if (! array_key_exists($locale, is_array($value) ? $value : [])) {
                continue;
            }

            $text = $value[$locale];

            if (is_string($text) && $text !== '' && trim($text) === '') {
                $validator->errors()->add("{$field}.{$locale}", 'Isian tidak boleh hanya berisi spasi.');
            }
        }
    }

    /** Expands "judul" into "judul (ID)" / "judul (EN)" for readable messages. */
    protected function translatableAttributes(array $labels): array
    {
        $out = [];
        $localeLabels = config('cms.locale_labels');

        foreach ($labels as $field => $label) {
            $out[$field] = $label;

            foreach (config('cms.locales') as $locale) {
                $out["{$field}.{$locale}"] = $label.' ('.($localeLabels[$locale] ?? strtoupper($locale)).')';
            }
        }

        return $out;
    }
}
