<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * There is no public registration (routes/auth.php), so the first account has
 * to come from the console — and later ones from an administrator who needs to
 * reset a password without a working mail server.
 *
 * Phase 2 adds the spatie role assignment here; the signature already accepts
 * `--role` so the command line does not change when it does.
 */
class CreateUser extends Command
{
    protected $signature = 'cms:user
        {--name= : Nama lengkap pengguna}
        {--email= : Alamat email untuk masuk}
        {--password= : Kata sandi; dibuat acak bila tidak diisi}
        {--role=super-admin : Peran yang diberikan (berlaku mulai Fase 2)}
        {--force : Perbarui pengguna yang sudah ada dengan email sama}';

    protected $description = 'Membuat atau memperbarui akun pengguna CMS';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Nama lengkap');
        $email = $this->option('email') ?: $this->ask('Email');

        // A generated password is safer than a typed one: it is strong, and it
        // is shown once here rather than living in shell history.
        $password = $this->option('password');
        $generated = $password === null;

        if ($generated) {
            $password = Str::password(16);
        }

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'password' => ['required', 'string', 'min:8'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        $existing = User::where('email', $email)->first();

        if ($existing && ! $this->option('force')) {
            $this->components->error("Pengguna dengan email {$email} sudah ada.");
            $this->line('  Gunakan <comment>--force</comment> untuk memperbarui nama dan kata sandinya.');

            return self::FAILURE;
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make($password)]
        );

        $this->newLine();
        $this->components->info($existing ? 'Pengguna diperbarui.' : 'Pengguna dibuat.');

        $this->components->twoColumnDetail('Nama', $user->name);
        $this->components->twoColumnDetail('Email', $user->email);

        if ($generated) {
            $this->components->twoColumnDetail('Kata sandi', "<comment>{$password}</comment>");
            $this->newLine();
            $this->components->warn('Kata sandi hanya ditampilkan sekali. Simpan sekarang.');
        }

        // Roles do not exist yet, so promising one would be a lie.
        if (! $this->rolesAvailable()) {
            $this->newLine();
            $this->components->warn(
                'Peran belum aktif (Fase 2). Untuk sekarang setiap akun punya akses penuh.'
            );
        }

        return self::SUCCESS;
    }

    private function rolesAvailable(): bool
    {
        return class_exists(\Spatie\Permission\Models\Role::class)
            && \Illuminate\Support\Facades\Schema::hasTable('roles');
    }
}
