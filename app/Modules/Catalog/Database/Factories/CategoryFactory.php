<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
final class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 99_999);
        $nameEn = "Category {$number}";

        return [
            Category::COLUMN_PARENT_ID => null,
            Category::COLUMN_NAME_FA => "دسته {$number}",
            Category::COLUMN_NAME_EN => $nameEn,
            Category::COLUMN_SLUG => Str::slug($nameEn),
            Category::COLUMN_DESCRIPTION => null,
            Category::COLUMN_SORT => 0,
            Category::COLUMN_IS_ACTIVE => true,
            Category::COLUMN_SEO_TITLE => null,
            Category::COLUMN_SEO_DESCRIPTION => null,
        ];
    }

    public function childOf(Category $parent): static
    {
        return $this->state(fn (array $attributes): array => [Category::COLUMN_PARENT_ID => $parent->getId()]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [Category::COLUMN_IS_ACTIVE => false]);
    }
}
