<?php

declare(strict_types=1);

use App\Modules\Identity\Database\Seeders\SuperAdminSeeder;
use App\Modules\Identity\Models\AdminUser;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    config(['auth.superadmin' => [
        'name' => 'مدیر ارشد',
        'email' => 'owner@pharmacy.test',
        'password' => 'a-strong-secret',
    ]]);
});

it('creates the super admin as an active admin user from the auth config', function (): void {
    $this->seed(SuperAdminSeeder::class);

    $admin = AdminUser::firstWhere('email', 'owner@pharmacy.test');

    expect($admin->name)->toBe('مدیر ارشد')
        ->and($admin->is_active)->toBeTrue()
        ->and(Hash::check('a-strong-secret', $admin->password))->toBeTrue()
        ->and($admin->hasTwoFactorAuthentication())->toBeFalse();
});

it('keeps a changed password and reactivates the admin when run again', function (): void {
    $this->seed(SuperAdminSeeder::class);
    AdminUser::firstWhere('email', 'owner@pharmacy.test')->forceFill(['password' => 'changed-later', 'is_active' => false])->save();

    $this->seed(SuperAdminSeeder::class);

    $admin = AdminUser::firstWhere('email', 'owner@pharmacy.test');
    expect(AdminUser::count())->toBe(1)
        ->and($admin->is_active)->toBeTrue()
        ->and(Hash::check('changed-later', $admin->password))->toBeTrue();
});

it('refuses the default password in production', function (): void {
    config(['auth.superadmin.password' => 'password']);
    $this->app['env'] = 'production';

    // Called directly: db:seed skips silently in production unless --force is given.
    $this->app->make(SuperAdminSeeder::class)->run();
})->throws(RuntimeException::class, 'SUPERADMIN_PASSWORD');

it('has a default email and password in the config', function (): void {
    $this->refreshApplication();

    expect(config('auth.superadmin.email'))->not->toBeEmpty()
        ->and(config('auth.superadmin.password'))->not->toBeEmpty();
});
