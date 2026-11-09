<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Database\Factories;

use App\Modules\Pricing\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Active suppliers by default. States: inactive().
 *
 * @extends Factory<Provider>
 */
final class ProviderFactory extends Factory
{
    protected $model = Provider::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            Provider::COLUMN_NAME => 'پخش '.fake()->unique()->numberBetween(1, 99_999),
            Provider::COLUMN_PHONE => '021'.fake()->numerify('########'),
            Provider::COLUMN_IS_ACTIVE => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [Provider::COLUMN_IS_ACTIVE => false]);
    }
}
