<?php

declare(strict_types=1);

use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Filament\Resources\AdminUsers\Pages\CreateAdminUser;
use App\Modules\Identity\Filament\Resources\AdminUsers\Pages\EditAdminUser;
use App\Modules\Identity\Filament\Resources\AdminUsers\Pages\ListAdminUsers;
use App\Modules\Identity\Filament\Resources\Roles\Pages\ListRoles;
use App\Modules\Identity\Models\AdminUser;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Role;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->superAdmin = AdminUser::factory()->withTwoFactorAuthentication()->superAdmin()->create(['name' => 'مدیر سیستم']);
    Role::findOrCreate(AdminRole::Operator->value, 'admin');
    Role::findOrCreate(AdminRole::ContentEditor->value, 'admin');
    $this->actingAs($this->superAdmin, 'admin');
});

it('creates an admin from the form through the create action', function (): void {
    livewire(CreateAdminUser::class)
        ->fillForm([
            'name' => 'سارا محمدی',
            'email' => 'Sara@Pharmacy.test',
            'password' => 'Daroo-Khane-1405!',
            'roles' => ['operator'],
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $admin = AdminUser::firstWhere('email', 'sara@pharmacy.test');
    expect($admin)->not->toBeNull()
        ->and($admin->getRoleNames()->all())->toBe(['operator']);
});

it('shows form errors for a weak password', function (): void {
    livewire(CreateAdminUser::class)
        ->fillForm(['name' => 'سارا', 'email' => 'sara@pharmacy.test', 'password' => 'password', 'roles' => []])
        ->call('create')
        ->assertHasFormErrors(['password']);

    expect(AdminUser::where('email', 'sara@pharmacy.test')->exists())->toBeFalse();
});

it('refuses to let the signed-in admin deactivate themselves and saves nothing', function (): void {
    livewire(EditAdminUser::class, ['record' => $this->superAdmin->getRouteKey()])
        ->fillForm(['is_active' => false])
        ->call('save')
        ->assertNotified('نمی‌توانید حساب خودتان را غیرفعال کنید.');

    expect($this->superAdmin->fresh()->is_active)->toBeTrue();
});

it('lists admins with Persian role names', function (): void {
    livewire(ListAdminUsers::class)
        ->assertCanSeeTableRecords([$this->superAdmin])
        ->assertSee('مدیر ارشد');
});

it('shows roles with Persian names and cannot delete built-in roles', function (): void {
    $operator = Role::findByName('operator', 'admin');

    livewire(ListRoles::class)
        ->assertSee(['مدیر ارشد', 'اپراتور', 'ویراستار محتوا'])
        ->assertTableActionHidden('delete', $operator);

    expect($operator->delete())->toBeFalse()
        ->and(Role::where('name', 'operator')->exists())->toBeTrue();
});
