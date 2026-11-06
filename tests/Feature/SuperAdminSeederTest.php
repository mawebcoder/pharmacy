<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class SuperAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.superadmin' => [
            'name' => 'مدیر ارشد',
            'email' => 'owner@pharmacy.test',
            'password' => 'a-strong-secret',
        ]]);
    }

    public function test_it_creates_the_super_admin_from_the_auth_config(): void
    {
        $this->seed(SuperAdminSeeder::class);

        $user = User::firstWhere('email', 'owner@pharmacy.test');

        $this->assertSame('مدیر ارشد', $user->name);
        $this->assertTrue($user->is_admin);
        $this->assertTrue(Hash::check('a-strong-secret', $user->password));
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_running_it_again_keeps_the_changed_password_and_restores_admin_access(): void
    {
        $this->seed(SuperAdminSeeder::class);
        User::firstWhere('email', 'owner@pharmacy.test')->forceFill(['password' => 'changed-later', 'is_admin' => false])->save();

        $this->seed(SuperAdminSeeder::class);

        $user = User::firstWhere('email', 'owner@pharmacy.test');
        $this->assertSame(1, User::count());
        $this->assertTrue($user->is_admin);
        $this->assertTrue(Hash::check('changed-later', $user->password));
    }

    public function test_the_default_password_is_refused_in_production(): void
    {
        config(['auth.superadmin.password' => 'password']);
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);

        // Called directly: db:seed skips silently in production unless --force is given.
        $this->app->make(SuperAdminSeeder::class)->run();
    }

    public function test_the_default_config_has_an_email_and_password(): void
    {
        $this->refreshApplication();

        $this->assertNotEmpty(config('auth.superadmin.email'));
        $this->assertNotEmpty(config('auth.superadmin.password'));
    }
}
