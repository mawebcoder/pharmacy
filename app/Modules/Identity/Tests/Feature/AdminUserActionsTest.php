<?php

declare(strict_types=1);

use App\Modules\Identity\Actions\CreateAdminUser;
use App\Modules\Identity\Actions\ResetAdminTwoFactor;
use App\Modules\Identity\Actions\UpdateAdminUser;
use App\Modules\Identity\Data\AdminUserData;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Exceptions\AdminUserChangeRefused;
use App\Modules\Identity\Models\AdminUser;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    foreach (AdminRole::cases() as $role) {
        Role::findOrCreate($role->value, 'admin');
    }
});

function adminData(array $overrides = []): AdminUserData
{
    return AdminUserData::fromArray([
        'name' => 'سارا محمدی',
        'email' => 'sara@pharmacy.test',
        'password' => 'Daroo-Khane-1405!',
        'roles' => [AdminRole::Operator->value],
        'is_active' => true,
        ...$overrides,
    ]);
}

it('creates an admin with a hashed password and the given roles', function (): void {
    $admin = app(CreateAdminUser::class)(adminData());

    expect($admin->email)->toBe('sara@pharmacy.test')
        ->and(Hash::check('Daroo-Khane-1405!', $admin->password))->toBeTrue()
        ->and($admin->getRoleNames()->all())->toBe(['operator'])
        ->and($admin->is_active)->toBeTrue();
});

it('refuses to create an admin without a password', function (): void {
    expect(fn (): AdminUser => app(CreateAdminUser::class)(adminData(['password' => null])))
        ->toThrow(InvalidArgumentException::class);
});

it('keeps the current password when the update has none', function (): void {
    $actor = AdminUser::factory()->superAdmin()->create();
    $admin = AdminUser::factory()->create();
    $oldHash = $admin->password;

    $updated = app(UpdateAdminUser::class)($admin, adminData(['password' => null, 'roles' => [AdminRole::ContentEditor->value]]), $actor);

    expect($updated->password)->toBe($oldHash)
        ->and($updated->getRoleNames()->all())->toBe(['content_editor']);
});

it('refuses to let admins deactivate themselves', function (): void {
    $actor = AdminUser::factory()->superAdmin()->create();
    AdminUser::factory()->superAdmin()->create();

    expect(fn (): AdminUser => app(UpdateAdminUser::class)($actor, adminData(['email' => $actor->email, 'roles' => ['super_admin'], 'is_active' => false]), $actor))
        ->toThrow(AdminUserChangeRefused::class, 'حساب خودتان');
});

it('refuses to let super admins drop their own super_admin role', function (): void {
    $actor = AdminUser::factory()->superAdmin()->create();
    AdminUser::factory()->superAdmin()->create();

    expect(fn (): AdminUser => app(UpdateAdminUser::class)($actor, adminData(['email' => $actor->email, 'roles' => ['operator']]), $actor))
        ->toThrow(AdminUserChangeRefused::class, 'مدیر ارشد');
});

it('keeps at least one active super admin', function (array $change): void {
    $actor = AdminUser::factory()->withRole(AdminRole::Operator)->create();
    $lastSuperAdmin = AdminUser::factory()->superAdmin()->create();
    AdminUser::factory()->superAdmin()->inactive()->create();

    expect(fn (): AdminUser => app(UpdateAdminUser::class)($lastSuperAdmin, adminData(['email' => $lastSuperAdmin->email, ...$change]), $actor))
        ->toThrow(AdminUserChangeRefused::class, 'دست‌کم یک مدیر ارشد');
})->with([
    'removing the role' => [['roles' => ['operator']]],
    'deactivating' => [['roles' => ['super_admin'], 'is_active' => false]],
]);

it('lets a super admin be demoted while another active super admin remains', function (): void {
    $actor = AdminUser::factory()->superAdmin()->create();
    $other = AdminUser::factory()->superAdmin()->create();

    $updated = app(UpdateAdminUser::class)($other, adminData(['email' => $other->email, 'roles' => ['operator']]), $actor);

    expect($updated->isSuperAdmin())->toBeFalse();
});

it('clears the authenticator app and recovery codes when 2FA is reset', function (): void {
    $admin = AdminUser::factory()->withTwoFactorAuthentication()->create();
    $admin->saveAppAuthenticationRecoveryCodes(['code-1', 'code-2']);

    app(ResetAdminTwoFactor::class)($admin);

    expect($admin->fresh()->hasTwoFactorAuthentication())->toBeFalse()
        ->and($admin->fresh()->getAppAuthenticationRecoveryCodes())->toBeNull();
});
