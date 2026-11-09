<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Database\Factories;

use App\Modules\Catalog\Database\Factories\ProductVariantFactory;
use App\Modules\Pricing\Enums\PriceChangeReason;
use App\Modules\Pricing\Models\VariantPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VariantPrice>
 */
final class VariantPriceFactory extends Factory
{
    protected $model = VariantPrice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            VariantPrice::COLUMN_VARIANT_ID => fn (): int => (int) ProductVariantFactory::new()->createOne()->getKey(),
            VariantPrice::COLUMN_PRICE => fake()->numberBetween(20, 900) * 10_000,
            VariantPrice::COLUMN_REASON => PriceChangeReason::Initial,
        ];
    }
}
