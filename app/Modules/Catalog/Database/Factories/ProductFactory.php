<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
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
            Product::COLUMN_PRIMARY_CATEGORY_ID => Category::factory(),
            Product::COLUMN_PRODUCT_TYPE => ProductType::Cosmetic,
            Product::COLUMN_REQUIRES_PRESCRIPTION => false,
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
        return $this->state(fn (array $attributes): array => [
            Product::COLUMN_PRODUCT_TYPE => ProductType::Rx,
            Product::COLUMN_REQUIRES_PRESCRIPTION => true,
        ]);
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
