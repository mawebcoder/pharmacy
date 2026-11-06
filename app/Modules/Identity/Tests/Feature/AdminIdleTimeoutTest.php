<?php

declare(strict_types=1);

use App\Modules\Identity\Models\AdminUser;

it('signs an admin out after 30 idle minutes', function (): void {
    $admin = AdminUser::factory()->withTwoFactorAuthentication()->create();
    $this->actingAs($admin, 'admin')->get('/admin')->assertOk();

    $this->travel(31)->minutes();

    $this->get('/admin')->assertRedirect('/admin/login');
    $this->assertGuest('admin');
});

it('keeps an active admin signed in', function (): void {
    $admin = AdminUser::factory()->withTwoFactorAuthentication()->create();
    $this->actingAs($admin, 'admin')->get('/admin')->assertOk();

    $this->travel(29)->minutes();

    $this->get('/admin')->assertOk();
    $this->assertAuthenticatedAs($admin, 'admin');
});
