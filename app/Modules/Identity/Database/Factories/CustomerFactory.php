<?php

declare(strict_types=1);

namespace App\Modules\Identity\Database\Factories;

use App\Modules\Identity\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Customers with a unique 0912… mobile. States: blocked(), named().
 *
 * @extends Factory<Customer>
 */
final class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            Customer::COLUMN_MOBILE => '0912'.fake()->unique()->numerify('#######'),
            Customer::COLUMN_IS_BLOCKED => false,
        ];
    }

    public function named(string $firstName = 'سارا', string $lastName = 'رضایی'): static
    {
        return $this->state(fn (array $attributes): array => [Customer::COLUMN_FIRST_NAME => $firstName, Customer::COLUMN_LAST_NAME => $lastName]);
    }

    public function blocked(): static
    {
        return $this->state(fn (array $attributes): array => [Customer::COLUMN_IS_BLOCKED => true]);
    }
}
