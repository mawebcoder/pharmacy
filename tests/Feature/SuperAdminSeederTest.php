<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    config(['auth.superadmin' => [
        'name' => 'مدیر ارشد',
        'email' => 'owner@pharmacy.test',
        'password' => 'a-strong-secret',
    ]]);
});

it('creates the super admin from the auth config', function (): void {
    $this->seed(SuperAdminSeeder::class);

    $user = User::firstWhere('email', 'owner@pharmacy.test');

    expect($user->name)->toBe('مدیر ارشد')
        ->and($user->is_admin)->toBeTrue()
        ->and(Hash::check('a-strong-secret', $user->password))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull();
});

it('keeps a changed password and restores admin access when run again', function (): void {
    $this->seed(SuperAdminSeeder::class);
    User::firstWhere('email', 'owner@pharmacy.test')->forceFill(['password' => 'changed-later', 'is_admin' => false])->save();

    $this->seed(SuperAdminSeeder::class);

    $user = User::firstWhere('email', 'owner@pharmacy.test');
    expect(User::count())->toBe(1)
        ->and($user->is_admin)->toBeTrue()
        ->and(Hash::check('changed-later', $user->password))->toBeTrue();
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
