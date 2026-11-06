<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;

it('lets an active super admin with two-factor authentication open Horizon', function (): void {
    $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->superAdmin()->create(), 'admin')
        ->get('/horizon/dashboard')
        ->assertOk();
});

it('refuses Horizon to everyone except active super admins with two-factor authentication', function (Closure $signIn): void {
    $signIn($this);

    $this->get('/horizon/dashboard')->assertForbidden();
})->with([
    'guest' => fn (): Closure => fn (): null => null,
    'super admin without two-factor authentication' => fn (): Closure => fn ($test) => $test->actingAs(AdminUser::factory()->superAdmin()->create(), 'admin'),
    'inactive super admin' => fn (): Closure => fn ($test) => $test->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->superAdmin()->inactive()->create(), 'admin'),
    'operator with two-factor authentication' => fn (): Closure => fn ($test) => $test->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::Operator)->create(), 'admin'),
    'customer' => fn (): Closure => fn ($test) => $test->actingAs(User::factory()->create(), 'web'),
]);
