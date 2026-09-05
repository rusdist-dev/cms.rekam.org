<?php

namespace App\Http\Requests\User;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('users.update');
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            // Blank means "leave the password alone" on an edit.
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
            'tenants' => ['array'],
            'tenants.*' => [Rule::exists(Tenant::class, 'slug')],
            'is_active' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama',
            'email' => 'email',
            'password' => 'kata sandi',
            'role' => 'peran',
            'tenants' => 'akses company',
            'is_active' => 'status aktif',
        ];
    }
}
