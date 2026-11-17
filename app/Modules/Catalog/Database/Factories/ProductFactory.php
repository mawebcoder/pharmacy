<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\Subcategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Draft cosmetics by default. States: active(), rx(), otc(), withVariants().
 *
 * @extends Factory<Product>
 */
final class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 999_999);
        $nameEn = "Product {$number}";

        return [
            Product::COLUMN_NAME_FA => "محصول {$number}",
            Product::COLUMN_NAME_EN => $nameEn,
            Product::COLUMN_SLUG => Str::slug($nameEn),
            Product::COLUMN_BRAND_ID => Brand::factory(),
            // Products belong to subcategories (owner, 2026-11-15); the category is the subcategory's.
            Product::COLUMN_PRIMARY_SUBCATEGORY_ID => Subcategory::factory(),
            Product::COLUMN_PRIMARY_CATEGORY_ID => fn (array $attributes): int => (int) Subcategory::query()->whereKey($attributes[Product::COLUMN_PRIMARY_SUBCATEGORY_ID])->value(Subcategory::COLUMN_CATEGORY_ID),
            Product::COLUMN_PRODUCT_TYPE => ProductType::Cosmetic,
            Product::COLUMN_STATUS => ProductStatus::Draft,
        ];
    }

    /**
     * Active in the database. This skips the activation checks (ActivateProduct); use it for storefront data.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [Product::COLUMN_STATUS => ProductStatus::Active]);
    }

    public function rx(): static
    {
        return $this->state(fn (array $attributes): array => [Product::COLUMN_PRODUCT_TYPE => ProductType::Rx]);
    }

    public function otc(): static
    {
        return $this->state(fn (array $attributes): array => [Product::COLUMN_PRODUCT_TYPE => ProductType::Otc]);
    }

    public function withVariants(int $count = 1): static
    {
        return $this->has(ProductVariant::factory()->count($count), 'variants');
    }
}
