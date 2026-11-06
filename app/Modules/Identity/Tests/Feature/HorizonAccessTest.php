<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\Models\AdminUser;

it('lets an active admin with two-factor authentication open Horizon', function (): void {
    $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->create(), 'admin')
        ->get('/horizon/dashboard')
        ->assertOk();
});

it('refuses Horizon to people who are not active admins with two-factor authentication', function (Closure $signIn): void {
    $signIn($this);

    $this->get('/horizon/dashboard')->assertForbidden();
})->with([
    'guest' => fn (): Closure => fn (): null => null,
    'admin without two-factor authentication' => fn (): Closure => fn ($test) => $test->actingAs(AdminUser::factory()->create(), 'admin'),
    'inactive admin' => fn (): Closure => fn ($test) => $test->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->inactive()->create(), 'admin'),
    'customer' => fn (): Closure => fn ($test) => $test->actingAs(User::factory()->create(), 'web'),
]);
