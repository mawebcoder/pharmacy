<?php

declare(strict_types=1);

namespace App\Modules\Locations\Database\Factories;

use App\Modules\Locations\Models\County;
use App\Modules\Locations\Models\Province;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<County>
 */
final class CountyFactory extends Factory
{
    protected $model = County::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'شهرستان '.fake()->unique()->numberBetween(1, 99_999);

        return [
            County::COLUMN_PROVINCE_ID => Province::factory(),
            County::COLUMN_NAME_FA => $name,
            County::COLUMN_SLUG => $name,
            County::COLUMN_SOURCE_ID => fake()->unique()->numberBetween(1_000_000, 9_999_999),
        ];
    }
}
