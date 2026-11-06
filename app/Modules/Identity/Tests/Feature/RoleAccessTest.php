<?php

declare(strict_types=1);

use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use Spatie\Permission\Models\Permission;

it('lets a super admin open every admin page', function (string $url): void {
    $admin = AdminUser::factory()->withTwoFactorAuthentication()->superAdmin()->create();

    $this->actingAs($admin, 'admin')->get($url)->assertOk();
})->with(['/admin/admin-users', '/admin/admin-users/create', '/admin/shield/roles', '/admin/activity-logs']);

it('forbids staff roles without permissions from the system pages', function (AdminRole $role, string $url): void {
    $admin = AdminUser::factory()->withTwoFactorAuthentication()->withRole($role)->create();

    $this->actingAs($admin, 'admin')->get($url)->assertForbidden();
})->with([AdminRole::Operator, AdminRole::ContentEditor])
    ->with(['/admin/admin-users', '/admin/admin-users/create', '/admin/shield/roles', '/admin/activity-logs']);

it('shows staff roles without permissions only the dashboard in the sidebar', function (AdminRole $role): void {
    $admin = AdminUser::factory()->withTwoFactorAuthentication()->withRole($role)->create();

    $this->actingAs($admin, 'admin')->get('/admin')
        ->assertOk()
        ->assertDontSee('مدیران')
        ->assertDontSee('گزارش فعالیت‌ها')
        ->assertDontSee('href="/horizon"', false);
})->with([AdminRole::Operator, AdminRole::ContentEditor]);

it('lets a role granted a permission do exactly that', function (): void {
    $admin = AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::Operator)->create();
    $admin->givePermissionTo(Permission::findOrCreate('ViewAny:AdminUser', 'admin'));

    $this->actingAs($admin, 'admin')->get('/admin/admin-users')->assertOk();
    $this->actingAs($admin, 'admin')->get('/admin/admin-users/create')->assertForbidden();
});
