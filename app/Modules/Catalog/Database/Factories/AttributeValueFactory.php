<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Models\AttributeValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttributeValue>
 */
final class AttributeValueFactory extends Factory
{
    protected $model = AttributeValue::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            AttributeValue::COLUMN_ATTRIBUTE_ID => Attribute::factory(),
            AttributeValue::COLUMN_VALUE_FA => 'مقدار '.fake()->unique()->numberBetween(1, 99_999),
            AttributeValue::COLUMN_SORT => 0,
        ];
    }
}
