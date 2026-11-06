<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\Models\AdminUser;
use Illuminate\Support\Facades\DB;

it('sends guests to the admin login page', function (): void {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('shows the admin login page in Persian, right to left', function (): void {
    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('dir="rtl"', false)
        ->assertSee('lang="fa"', false);
});

it('forces an admin without two-factor authentication to set it up first', function (): void {
    $admin = AdminUser::factory()->create();

    $this->actingAs($admin, 'admin')
        ->get('/admin')
        ->assertRedirect('/admin/multi-factor-authentication/set-up');
});

it('shows the dashboard to an admin who has set up two-factor authentication', function (): void {
    $admin = AdminUser::factory()->withTwoFactorAuthentication()->create();

    $this->actingAs($admin, 'admin')
        ->get('/admin')
        ->assertOk()
        ->assertSee('داشبورد');
});

it('forbids an inactive admin', function (): void {
    $admin = AdminUser::factory()->withTwoFactorAuthentication()->inactive()->create();

    $this->actingAs($admin, 'admin')
        ->get('/admin')
        ->assertForbidden();
});

it('does not let a signed-in customer into the admin panel', function (): void {
    $this->actingAs(User::factory()->create(), 'web')
        ->get('/admin')
        ->assertRedirect('/admin/login');
});

it('shows the System group with the Horizon link to a super admin', function (): void {
    $admin = AdminUser::factory()->withTwoFactorAuthentication()->superAdmin()->create();

    $this->actingAs($admin, 'admin')
        ->get('/admin')
        ->assertOk()
        ->assertSee('سیستم')
        ->assertSee('صف‌ها (Horizon)')
        ->assertSee('href="/horizon"', false);
});

it('stores the two-factor secret encrypted', function (): void {
    $admin = AdminUser::factory()->withTwoFactorAuthentication()->create();

    $stored = DB::table('admin_users')->where('id', $admin->id)->value('app_authentication_secret');

    expect($stored)->not->toBe($admin->getAppAuthenticationSecret())
        ->and(decrypt($stored, unserialize: false))->toBe($admin->getAppAuthenticationSecret());
});

it('records when an admin signs in', function (): void {
    $this->travelTo(now()->startOfMinute());
    $admin = AdminUser::factory()->withTwoFactorAuthentication()->create();

    auth('admin')->login($admin);

    expect($admin->fresh()->last_login_at?->equalTo(now()))->toBeTrue();
});
