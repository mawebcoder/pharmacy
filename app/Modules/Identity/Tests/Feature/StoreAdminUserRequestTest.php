<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Requests\StoreAdminUserRequest;
use App\Modules\Identity\Models\AdminUser;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

function adminInput(array $overrides = []): array
{
    return ['name' => 'سارا', 'email' => 'sara@pharmacy.test', 'password' => 'Daroo-Khane-1405!', 'roles' => [], 'is_active' => true, ...$overrides];
}

it('requires a strong password when creating an admin', function (?string $password): void {
    $validator = Validator::make(adminInput(['password' => $password]), StoreAdminUserRequest::baseRules());

    expect($validator->errors()->has('password'))->toBeTrue();
})->with(['missing' => [null], 'weak' => ['password']]);

it('lets an update leave the password empty', function (): void {
    $admin = AdminUser::factory()->create();

    $validator = Validator::make(adminInput(['email' => $admin->email, 'password' => null]), StoreAdminUserRequest::baseRules($admin->id));

    expect($validator->passes())->toBeTrue();
});

it('rejects an email another admin already uses', function (): void {
    AdminUser::factory()->create(['email' => 'sara@pharmacy.test']);

    $validator = Validator::make(adminInput(), StoreAdminUserRequest::baseRules());

    expect($validator->errors()->has('email'))->toBeTrue();
});

it('accepts only roles of the admin guard', function (): void {
    Role::findOrCreate('operator', 'admin');
    Role::findOrCreate('customer-club', 'web');

    $valid = Validator::make(adminInput(['roles' => ['operator']]), StoreAdminUserRequest::baseRules());
    $invalid = Validator::make(adminInput(['roles' => ['customer-club']]), StoreAdminUserRequest::baseRules());

    expect($valid->passes())->toBeTrue()
        ->and($invalid->errors()->has('roles.0'))->toBeTrue();
});
