<?php

declare(strict_types=1);

use App\Modules\Identity\Filament\Auth\EditAdminProfile;
use App\Modules\Identity\Models\AdminUser;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->admin = AdminUser::factory()->withTwoFactorAuthentication()->create(['name' => 'سارا', 'email' => 'sara@pharmacy.test']);
    $this->actingAs($this->admin, 'admin');
});

it('saves a new name and email from the profile page', function (): void {
    livewire(EditAdminProfile::class)
        ->fillForm(['name' => 'سارا محمدی', 'email' => 'sara.m@pharmacy.test', 'currentPassword' => 'password'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->admin->fresh())
        ->getName()->toBe('سارا محمدی')
        ->getEmail()->toBe('sara.m@pharmacy.test');
});

it('changes the password from the profile page', function (): void {
    livewire(EditAdminProfile::class)
        ->fillForm(['password' => 'Daroo-Khane-1405!', 'passwordConfirmation' => 'Daroo-Khane-1405!', 'currentPassword' => 'password'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Hash::check('Daroo-Khane-1405!', $this->admin->fresh()->getPassword()))->toBeTrue();
});
