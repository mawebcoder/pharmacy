<?php

declare(strict_types=1);

namespace App\Modules\Identity\Database\Factories;

use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<AdminUser>
 */
final class AdminUserFactory extends Factory
{
    protected $model = AdminUser::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            AdminUser::COLUMN_NAME => fake()->name(),
            AdminUser::COLUMN_EMAIL => fake()->unique()->safeEmail(),
            AdminUser::COLUMN_PASSWORD => self::$password ??= Hash::make('password'),
            AdminUser::COLUMN_IS_ACTIVE => true,
            AdminUser::COLUMN_REMEMBER_TOKEN => Str::random(10),
        ];
    }

    /**
     * An admin who has set up an authenticator app (TOTP).
     */
    public function withTwoFactorAuthentication(): static
    {
        return $this->state(fn (array $attributes): array => [
            AdminUser::COLUMN_APP_AUTHENTICATION_SECRET => (new Google2FA)->generateSecretKey(),
        ]);
    }

    /**
     * An admin with the given role (the roles are created on demand on the "admin" guard).
     */
    public function withRole(AdminRole $role): static
    {
        return $this->afterCreating(function (AdminUser $admin) use ($role): void {
            Role::findOrCreate($role->value, 'admin');
            $admin->assignRole($role->value);
        });
    }

    public function superAdmin(): static
    {
        return $this->withRole(AdminRole::SuperAdmin);
    }

    /**
     * An admin whose access has been switched off.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            AdminUser::COLUMN_IS_ACTIVE => false,
        ]);
    }
}
