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
        return [
            // A Tipax-like stateId.
            Province::COLUMN_ID => fake()->unique()->numberBetween(1, 9_999_999),
            Province::COLUMN_NAME_FA => 'استان '.fake()->unique()->numberBetween(1, 99_999),
        ];
    }
}
