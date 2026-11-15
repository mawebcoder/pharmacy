<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Subcategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Subcategory>
 */
final class SubcategoryFactory extends Factory
{
    protected $model = Subcategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 99_999);
        $nameEn = "Subcategory {$number}";

        return [
            Subcategory::COLUMN_CATEGORY_ID => Category::factory(),
            Subcategory::COLUMN_NAME_FA => "زیردسته {$number}",
            Subcategory::COLUMN_NAME_EN => $nameEn,
            Subcategory::COLUMN_SLUG => Str::slug($nameEn),
            Subcategory::COLUMN_DESCRIPTION => null,
            Subcategory::COLUMN_SORT => 0,
            Subcategory::COLUMN_IS_ACTIVE => true,
            Subcategory::COLUMN_SEO_TITLE => null,
            Subcategory::COLUMN_SEO_DESCRIPTION => null,
        ];
    }

    public function of(Category $category): static
    {
        return $this->state(fn (array $attributes): array => [Subcategory::COLUMN_CATEGORY_ID => $category->getId()]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [Subcategory::COLUMN_IS_ACTIVE => false]);
    }
}
