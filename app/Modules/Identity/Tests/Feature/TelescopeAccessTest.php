<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use App\Providers\TelescopeServiceProvider;

it('leaves Telescope out of every environment but local', function (): void {
    $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->superAdmin()->create(), 'admin')
        ->get('/telescope/requests')
        ->assertNotFound();
});

describe('when registered (APP_ENV=local)', function (): void {
    beforeEach(function (): void {
        config(['telescope.enabled' => true]);
        $this->app->register(Laravel\Telescope\TelescopeServiceProvider::class);
        $this->app->register(TelescopeServiceProvider::class);
    });

    it('lets an active super admin with two-factor authentication open Telescope', function (): void {
        $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->superAdmin()->create(), 'admin')
            ->get('/telescope/requests')
            ->assertOk()
            ->assertSee('Telescope');
    });

    it('refuses Telescope to everyone except active super admins with two-factor authentication', function (Closure $signIn): void {
        $signIn($this);

        $this->get('/telescope/requests')->assertForbidden();
    })->with([
        'guest' => fn (): Closure => fn (): null => null,
        'super admin without two-factor authentication' => fn (): Closure => fn ($test) => $test->actingAs(AdminUser::factory()->superAdmin()->create(), 'admin'),
        'operator with two-factor authentication' => fn (): Closure => fn ($test) => $test->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::Operator)->create(), 'admin'),
        'customer' => fn (): Closure => fn ($test) => $test->actingAs(User::factory()->create(), 'web'),
    ]);
});
