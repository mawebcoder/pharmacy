<?php

declare(strict_types=1);

namespace App\Modules\Identity\Database\Factories;

use App\Modules\Identity\Models\AdminUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

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
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => self::$password ??= Hash::make('password'),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * An admin who has set up an authenticator app (TOTP).
     */
    public function withTwoFactorAuthentication(): static
    {
        return $this->state(fn (array $attributes): array => [
            'app_authentication_secret' => (new Google2FA)->generateSecretKey(),
        ]);
    }

    /**
     * An admin whose access has been switched off.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
