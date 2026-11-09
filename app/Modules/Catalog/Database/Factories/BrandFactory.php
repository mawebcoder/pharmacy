<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Active brands with a 10 % commission. States: inactive(), withoutCommission().
 *
 * @extends Factory<Brand>
 */
final class BrandFactory extends Factory
{
    protected $model = Brand::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 99_999);
        $nameEn = "Brand {$number}";

        return [
            Brand::COLUMN_NAME_FA => "برند {$number}",
            Brand::COLUMN_NAME_EN => $nameEn,
            Brand::COLUMN_SLUG => Str::slug($nameEn),
            Brand::COLUMN_COUNTRY => fake()->randomElement(['DE', 'US', 'GB', 'IR', 'CH', 'FR']),
            Brand::COLUMN_DESCRIPTION => null,
            Brand::COLUMN_COMMISSION_PERCENT => 10,
            Brand::COLUMN_IS_ACTIVE => true,
            Brand::COLUMN_SEO_TITLE => null,
            Brand::COLUMN_SEO_DESCRIPTION => null,
        ];
    }

    public function withoutCommission(): static
    {
        return $this->state(fn (array $attributes): array => [Brand::COLUMN_COMMISSION_PERCENT => null]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [Brand::COLUMN_IS_ACTIVE => false]);
    }
}
