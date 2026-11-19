<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Actions;

use App\Modules\Shipping\Data\ShippingFallbackRateData;
use App\Modules\Shipping\Models\ShippingFallbackRate;

/**
 * Adds or changes one weight band of «نرخ ارسال جایگزین» (roadmap S31, owner 2026-11-19).
 */
final class SaveShippingFallbackRate
{
    #[\NoDiscard]
    public function __invoke(ShippingFallbackRateData $data, ?ShippingFallbackRate $rate = null): ShippingFallbackRate
    {
        $rate ??= new ShippingFallbackRate;
        $rate->setMaxWeightG($data->maxWeightG)
            ->setSameCityPrice($data->sameCityPrice)
            ->setSameProvincePrice($data->sameProvincePrice)
            ->setOtherProvincePrice($data->otherProvincePrice)
            ->save();

        return $rate;
    }
}
