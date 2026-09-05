<?php

namespace App\Http\Requests\Role;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $role
            ? $this->user()->can('roles.update')
            : $this->user()->can('roles.create');
    }

    public function rules(): array
    {
        $role = $this->route('role');

        return [
            'name' => [
                'required', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/',
                Rule::unique('roles', 'name')->ignore($role),
                // Creating a second "super-admin" would be a role that looks
                // privileged but is not the one Gate::before recognises.
                Rule::notIn($role?->name === User::SUPER_ADMIN ? [] : [User::SUPER_ADMIN]),
            ],
            'permissions' => ['array'],
            'permissions.*' => [Rule::exists('permissions', 'name')],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'Slug hanya boleh huruf kecil, angka, dan tanda hubung.',
            'name.not_in' => 'Nama peran tersebut dicadangkan sistem.',
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'slug peran', 'permissions' => 'izin'];
    }
}
