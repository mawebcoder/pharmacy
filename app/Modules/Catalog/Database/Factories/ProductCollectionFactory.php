<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\ProductCollection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductCollection>
 */
final class ProductCollectionFactory extends Factory
{
    protected $model = ProductCollection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 99_999);
        $nameEn = "Collection {$number}";

        return [
            ProductCollection::COLUMN_NAME_FA => "کالکشن {$number}",
            ProductCollection::COLUMN_NAME_EN => $nameEn,
            ProductCollection::COLUMN_SLUG => Str::slug($nameEn),
            ProductCollection::COLUMN_IS_ACTIVE => true,
            ProductCollection::COLUMN_SORT => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [ProductCollection::COLUMN_IS_ACTIVE => false]);
    }

    /**
     * A collection whose schedule ended yesterday.
     */
    public function ended(): static
    {
        return $this->state(fn (array $attributes): array => [ProductCollection::COLUMN_ENDS_AT => now()->subDay()]);
    }
}
