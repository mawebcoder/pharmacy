<?php

declare(strict_types=1);

namespace App\Modules\Locations\Database\Factories;

use App\Modules\Locations\Models\City;
use App\Modules\Locations\Models\Province;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<City>
 */
final class CityFactory extends Factory
{
    protected $model = City::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // A Tipax-like city id.
            City::COLUMN_ID => fake()->unique()->numberBetween(1, 9_999_999),
            City::COLUMN_PROVINCE_ID => Province::factory(),
            City::COLUMN_NAME_FA => 'شهر '.fake()->unique()->numberBetween(1, 99_999),
            City::COLUMN_HAS_TIPAX_BRANCH => true,
            City::COLUMN_IS_DELIVERY_ENABLED => false,
        ];
    }

    public function deliveryEnabled(): static
    {
        return $this->state(fn (array $attributes): array => [City::COLUMN_IS_DELIVERY_ENABLED => true]);
    }
}
