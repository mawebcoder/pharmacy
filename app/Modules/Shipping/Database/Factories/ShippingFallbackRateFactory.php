<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Database\Factories;

use App\Modules\Shipping\Models\ShippingFallbackRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingFallbackRate>
 */
final class ShippingFallbackRateFactory extends Factory
{
    protected $model = ShippingFallbackRate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            ShippingFallbackRate::COLUMN_MAX_WEIGHT_G => 1000,
            ShippingFallbackRate::COLUMN_SAME_CITY_PRICE => 900_000,
            ShippingFallbackRate::COLUMN_SAME_PROVINCE_PRICE => 1_200_000,
            ShippingFallbackRate::COLUMN_OTHER_PROVINCE_PRICE => 1_500_000,
        ];
    }
}
