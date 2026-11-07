<?php

declare(strict_types=1);

namespace App\Modules\Locations\Database\Factories;

use App\Modules\Locations\Models\Province;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Province>
 */
final class ProvinceFactory extends Factory
{
    protected $model = Province::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'استان '.fake()->unique()->numberBetween(1, 99_999);

        return [
            Province::COLUMN_NAME_FA => $name,
            Province::COLUMN_SLUG => $name,
            Province::COLUMN_TEL_PREFIX => '0'.fake()->numberBetween(11, 87),
            Province::COLUMN_SOURCE_ID => fake()->unique()->numberBetween(100, 99_999),
        ];
    }
}
