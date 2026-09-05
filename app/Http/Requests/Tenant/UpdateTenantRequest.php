<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Only capability and presentation are editable. Slug and db_name are not:
 * changing either would point the CMS at a different database while the old
 * one still holds the content (context.md §5.2).
 */
class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('tenants.update');
    }

    public function rules(): array
    {
        $features = array_keys(config('cms.features'));

        return [
            'name' => ['required', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],

            // Reject unknown keys rather than storing flags nothing reads.
            'features' => ['array', function (string $attribute, $value, callable $fail) use ($features) {
                $unknown = array_diff(array_keys((array) $value), $features);

                if ($unknown) {
                    $fail('Modul tidak dikenal: '.implode(', ', $unknown).'.');
                }
            }],
            'features.*' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama company',
            'domain' => 'domain',
            'features' => 'modul aktif',
            'is_active' => 'status aktif',
        ];
    }
}
