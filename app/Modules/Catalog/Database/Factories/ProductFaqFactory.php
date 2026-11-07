<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductFaq;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductFaq>
 */
final class ProductFaqFactory extends Factory
{
    protected $model = ProductFaq::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            ProductFaq::COLUMN_PRODUCT_ID => Product::factory(),
            ProductFaq::COLUMN_QUESTION => 'این محصول برای چه پوستی مناسب است؟',
            ProductFaq::COLUMN_ANSWER => 'برای پوست خشک و حساس.',
            ProductFaq::COLUMN_SORT => 0,
        ];
    }
}
