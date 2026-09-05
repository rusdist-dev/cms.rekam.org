<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The first account, so a fresh install can be logged into.
 *
 * Idempotent: re-running never resets the password of an account that already
 * exists, which matters because `db:seed` runs again on every deploy that adds
 * a seeder. It does re-assert the role and tenant access, so a super-admin
 * cannot end up locked out of a newly added company.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = $this->config('ADMIN_EMAIL', 'admin@rekam.org');
        $user = User::where('email', $email)->first();

        if ($user) {
            $this->command?->info("Pengguna {$email} sudah ada — kata sandi dipertahankan.");
        } else {
            $user = $this->create($email);
        }

        // A super-admin reaches every tenant through the role, but the pivot is
        // still synced so demoting them later does not orphan their access.
        $user->syncRoles([User::SUPER_ADMIN]);
        $user->tenants()->sync(Tenant::pluck('id'));
    }

    private function create(string $email): User
    {
        // A blank ADMIN_PASSWORD must never become a blank password: an unset
        // and an empty variable are both treated as "generate one for me".
        $password = $this->config('ADMIN_PASSWORD');
        $generated = $password === null || strlen($password) < 8;

        if ($generated) {
            $password = Str::password(16);
        }

        $user = User::create([
            'name' => $this->config('ADMIN_NAME', 'Administrator'),
            'email' => $email,
            'password' => Hash::make($password),
            'is_active' => true,
        ]);

        $this->command?->info("Pengguna admin dibuat: {$user->email}");

        if ($generated) {
            $this->command?->warn("Kata sandi dibuat otomatis: {$password}");
            $this->command?->warn('Hanya ditampilkan sekali. Simpan sekarang, atau set ADMIN_PASSWORD (minimal 8 karakter) di .env sebelum seeding.');
        }

        return $user;
    }

    /** Reads an env var, treating an empty value the same as an absent one. */
    private function config(string $key, ?string $default = null): ?string
    {
        $value = env($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : $default;
    }
}
