<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * This command is the only way into a fresh install, so it has to work on a
 * database with nothing in it and it must never quietly overwrite an account.
 */
class CreateUserCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // AdminUserSeeder assigns the super-admin role, so the roles have to
        // exist before it runs — the same order DatabaseSeeder uses.
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }

    public function test_it_creates_a_user_with_the_given_password(): void
    {
        $this->artisan('cms:user', [
            '--name' => 'Budi Santoso',
            '--email' => 'budi@rekam.org',
            '--password' => 'rahasia-sekali',
        ])->assertSuccessful();

        $user = User::where('email', 'budi@rekam.org')->first();

        $this->assertNotNull($user);
        $this->assertSame('Budi Santoso', $user->name);
        $this->assertTrue(Hash::check('rahasia-sekali', $user->password));
    }

    public function test_the_created_user_can_actually_log_in(): void
    {
        $this->artisan('cms:user', [
            '--name' => 'Admin',
            '--email' => 'admin@rekam.org',
            '--password' => 'rahasia-sekali',
        ])->assertSuccessful();

        $this->post(route('login'), [
            'email' => 'admin@rekam.org',
            'password' => 'rahasia-sekali',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    public function test_it_generates_a_password_when_none_is_given(): void
    {
        $this->artisan('cms:user', ['--name' => 'Ani', '--email' => 'ani@rekam.org'])
            ->expectsOutputToContain('Kata sandi hanya ditampilkan sekali')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'ani@rekam.org']);
    }

    public function test_it_refuses_to_overwrite_an_existing_account_by_accident(): void
    {
        User::factory()->create(['email' => 'ada@rekam.org', 'name' => 'Ada']);

        $this->artisan('cms:user', [
            '--name' => 'Bukan Ada',
            '--email' => 'ada@rekam.org',
            '--password' => 'rahasia-sekali',
        ])->assertFailed();

        $this->assertSame('Ada', User::where('email', 'ada@rekam.org')->first()->name);
    }

    public function test_force_updates_the_existing_account(): void
    {
        User::factory()->create(['email' => 'ada@rekam.org', 'name' => 'Ada']);

        $this->artisan('cms:user', [
            '--name' => 'Ada Lovelace',
            '--email' => 'ada@rekam.org',
            '--password' => 'sandi-baru-123',
            '--force' => true,
        ])->assertSuccessful();

        $user = User::where('email', 'ada@rekam.org')->first();

        $this->assertSame('Ada Lovelace', $user->name);
        $this->assertTrue(Hash::check('sandi-baru-123', $user->password));
        $this->assertSame(1, User::count());
    }

    public function test_it_rejects_a_bad_email_or_a_short_password(): void
    {
        $this->artisan('cms:user', [
            '--name' => 'X',
            '--email' => 'bukan-email',
            '--password' => 'rahasia-sekali',
        ])->assertFailed();

        $this->artisan('cms:user', [
            '--name' => 'X',
            '--email' => 'x@rekam.org',
            '--password' => 'pendek',
        ])->assertFailed();

        $this->assertSame(0, User::count());
    }

    public function test_the_seeder_creates_an_admin_and_is_safe_to_rerun(): void
    {
        $this->seed(\Database\Seeders\AdminUserSeeder::class);

        $admin = User::where('email', config('app.admin_email', 'admin@rekam.org'))->first()
            ?? User::first();

        $this->assertNotNull($admin);
        $original = $admin->password;

        // Re-seeding must not reset a password an administrator has changed.
        $this->seed(\Database\Seeders\AdminUserSeeder::class);

        $this->assertSame(1, User::count());
        $this->assertSame($original, $admin->fresh()->password);
    }

    public function test_the_seeder_never_creates_a_blank_password(): void
    {
        // An empty ADMIN_PASSWORD in .env reads as "" rather than falling back
        // to a default, so it must be treated as absent, not accepted.
        putenv('ADMIN_PASSWORD=');
        $_ENV['ADMIN_PASSWORD'] = '';

        $this->seed(\Database\Seeders\AdminUserSeeder::class);

        $user = User::first();

        $this->assertNotNull($user);
        $this->assertFalse(Hash::check('', $user->password));
    }

    public function test_a_short_env_password_is_replaced_rather_than_used(): void
    {
        putenv('ADMIN_PASSWORD=123');
        $_ENV['ADMIN_PASSWORD'] = '123';

        $this->seed(\Database\Seeders\AdminUserSeeder::class);

        $this->assertFalse(Hash::check('123', User::first()->password));
    }
}
