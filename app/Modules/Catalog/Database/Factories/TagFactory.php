<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Enums\TagType;
use App\Modules\Catalog\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tag>
 */
final class TagFactory extends Factory
{
    protected $model = Tag::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 99_999);
        $nameEn = "Tag {$number}";

        return [
            Tag::COLUMN_NAME_FA => "برچسب {$number}",
            Tag::COLUMN_NAME_EN => $nameEn,
            Tag::COLUMN_SLUG => Str::slug($nameEn),
            Tag::COLUMN_TYPE => TagType::Nutrient,
            Tag::COLUMN_IS_FEATURED => false,
            Tag::COLUMN_SORT => 0,
        ];
    }

    public function featured(int $sort = 0): static
    {
        return $this->state(fn (array $attributes): array => [Tag::COLUMN_IS_FEATURED => true, Tag::COLUMN_SORT => $sort]);
    }
}
