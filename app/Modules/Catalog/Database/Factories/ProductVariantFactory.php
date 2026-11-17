<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Enums\StockStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
final class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $officialPrice = fake()->numberBetween(20, 900) * 10_000;

        return [
            ProductVariant::COLUMN_PRODUCT_ID => Product::factory(),
            ProductVariant::COLUMN_SKU => 'SKU-'.fake()->unique()->numerify('######'),
            ProductVariant::COLUMN_TITLE_FA => fake()->randomElement(['۳۰ عددی', '۶۰ عددی', '۵۰ میلی‌لیتر', '۲۰۰ میلی‌لیتر']),
            ProductVariant::COLUMN_OFFICIAL_PRICE => $officialPrice,
            ProductVariant::COLUMN_PRICE => $officialPrice,
            ProductVariant::COLUMN_STOCK_STATUS => StockStatus::Available,
            ProductVariant::COLUMN_WEIGHT_G => fake()->numberBetween(50, 800),
            ProductVariant::COLUMN_IS_ACTIVE => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [ProductVariant::COLUMN_IS_ACTIVE => false]);
    }

    /**
     * The selling supplier's stock is «زودمصرف», expiring on $expiryDate (Y-m-d; default: in two months).
     */
    public function nearExpiry(?string $expiryDate = null): static
    {
        return $this->state(fn (array $attributes): array => [
            ProductVariant::COLUMN_IS_NEAR_EXPIRY => true,
            ProductVariant::COLUMN_EXPIRY_DATE => $expiryDate ?? now()->addMonths(2)->toDateString(),
        ]);
    }
}
