<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Data;

use App\Modules\Shipping\Models\ShippingFallbackRate;

/**
 * One weight band of «نرخ ارسال جایگزین» from the admin form (roadmap S31): up to which weight (g) and the price per
 * distance (rial).
 */
final readonly class ShippingFallbackRateData
{
    public function __construct(
        public int $maxWeightG,
        public int $sameCityPrice,
        public int $sameProvincePrice,
        public int $otherProvincePrice,
    ) {}

    /**
     * @param  array<string, mixed>  $data  keys are the shipping_fallback_rates columns (ShippingFallbackRate::COLUMN_*)
     */
    public static function fromArray(array $data): self
    {
        return new self(
            maxWeightG: (int) ($data[ShippingFallbackRate::COLUMN_MAX_WEIGHT_G] ?? 0),
            sameCityPrice: (int) ($data[ShippingFallbackRate::COLUMN_SAME_CITY_PRICE] ?? 0),
            sameProvincePrice: (int) ($data[ShippingFallbackRate::COLUMN_SAME_PROVINCE_PRICE] ?? 0),
            otherProvincePrice: (int) ($data[ShippingFallbackRate::COLUMN_OTHER_PROVINCE_PRICE] ?? 0),
        );
    }
}
