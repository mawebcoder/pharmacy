<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Enums\AttributeType;
use App\Modules\Catalog\Models\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attribute>
 */
final class AttributeFactory extends Factory
{
    protected $model = Attribute::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 99_999);

        return [
            Attribute::COLUMN_NAME_FA => "ویژگی {$number}",
            Attribute::COLUMN_CODE => "attribute_{$number}",
            Attribute::COLUMN_TYPE => AttributeType::Select,
            Attribute::COLUMN_IS_FILTERABLE => true,
            Attribute::COLUMN_SORT => 0,
        ];
    }

    public function multiple(): static
    {
        return $this->state(fn (array $attributes): array => [Attribute::COLUMN_TYPE => AttributeType::MultiSelect]);
    }
}
