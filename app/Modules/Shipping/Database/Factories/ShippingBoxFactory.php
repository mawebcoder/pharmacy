<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Database\Factories;

use App\Modules\Shipping\Models\ShippingBox;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An active 20 × 15 × 10 box of 100 g that takes up to 6 kg. States: inactive().
 *
 * @extends Factory<ShippingBox>
 */
final class ShippingBoxFactory extends Factory
{
    protected $model = ShippingBox::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            ShippingBox::COLUMN_NAME => 'کارتن آزمایشی',
            ShippingBox::COLUMN_LENGTH_CM => 20,
            ShippingBox::COLUMN_WIDTH_CM => 15,
            ShippingBox::COLUMN_HEIGHT_CM => 10,
            ShippingBox::COLUMN_EMPTY_WEIGHT_G => 100,
            ShippingBox::COLUMN_MAX_WEIGHT_G => 6000,
            ShippingBox::COLUMN_SORT => 3000,
            ShippingBox::COLUMN_IS_ACTIVE => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [ShippingBox::COLUMN_IS_ACTIVE => false]);
    }
}
