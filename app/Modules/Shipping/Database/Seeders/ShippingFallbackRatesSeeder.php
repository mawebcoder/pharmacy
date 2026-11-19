<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Database\Seeders;

use App\Modules\Shipping\Models\ShippingFallbackRate;
use Illuminate\Database\Seeder;

/**
 * Starting fallback shipping prices (roadmap S31, owner 2026-11-19), for the admin to correct in «نرخ ارسال جایگزین».
 * Rial. Adds only the bands that are missing; edited rows are kept.
 */
final class ShippingFallbackRatesSeeder extends Seeder
{
    /**
     * Up to grams => [same city, same province, other province].
     */
    private const array RATES = [
        1000 => [900_000, 1_200_000, 1_500_000],
        3000 => [1_100_000, 1_500_000, 1_900_000],
        6000 => [1_400_000, 1_900_000, 2_400_000],
    ];

    public function run(): void
    {
        foreach (self::RATES as $grams => [$sameCity, $sameProvince, $otherProvince]) {
            if (ShippingFallbackRate::query()->where(ShippingFallbackRate::COLUMN_MAX_WEIGHT_G, $grams)->exists()) {
                continue;
            }

            (new ShippingFallbackRate)
                ->setMaxWeightG($grams)
                ->setSameCityPrice($sameCity)
                ->setSameProvincePrice($sameProvince)
                ->setOtherProvincePrice($otherProvince)
                ->save();
        }
    }
}
