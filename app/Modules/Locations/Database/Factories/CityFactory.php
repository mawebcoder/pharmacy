<?php

declare(strict_types=1);

namespace App\Modules\Locations\Database\Factories;

use App\Modules\Locations\Enums\CityType;
use App\Modules\Locations\Models\City;
use App\Modules\Locations\Models\County;
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
        $name = 'شهر '.fake()->unique()->numberBetween(1, 99_999);

        return [
            City::COLUMN_COUNTY_ID => County::factory(),
            City::COLUMN_PROVINCE_ID => fn (array $attributes): int => (int) County::query()
                ->whereKey($attributes[City::COLUMN_COUNTY_ID])
                ->value(County::COLUMN_PROVINCE_ID),
            City::COLUMN_NAME_FA => $name,
            City::COLUMN_SLUG => $name,
            City::COLUMN_TYPE => CityType::City,
            City::COLUMN_LATITUDE => null,
            City::COLUMN_LONGITUDE => null,
            City::COLUMN_IS_DELIVERY_ENABLED => false,
            City::COLUMN_SOURCE_ID => fake()->unique()->numberBetween(1_000_000_000_000, 9_999_999_999_999),
        ];
    }

    public function deliveryEnabled(): static
    {
        return $this->state(fn (array $attributes): array => [City::COLUMN_IS_DELIVERY_ENABLED => true]);
    }
}
