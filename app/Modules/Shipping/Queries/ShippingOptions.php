<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Queries;

use App\Modules\Settings\Queries\StoreSettings;
use App\Modules\Shipping\Data\PackItem;
use App\Modules\Shipping\Data\ShippingOptionData;
use App\Modules\Shipping\Data\ShippingPriceRequest;
use App\Modules\Shipping\Packing\BoxPacker;
use App\Modules\Shipping\Pricing\ShippingPriceCalculator;
use App\Modules\Shipping\Pricing\Strategies\FlatRateShippingPrice;

/**
 * The ways to ship a cart to a city, priced (roadmap S34): the items are packed into our boxes the same way a paid
 * order is (S31), sent from the warehouse city in the store settings, and priced by every shipping service. Without
 * a destination yet, only the flat rate.
 */
final readonly class ShippingOptions
{
    public function __construct(
        private BoxPacker $packer,
        private ActiveBoxes $boxes,
        private ShippingPriceCalculator $calculator,
        private StoreSettings $settings,
    ) {}

    /**
     * @param  list<PackItem>  $items
     * @return list<ShippingOptionData> cheapest first, never empty
     */
    public function for(?int $destinationCityId, array $items): array
    {
        if ($destinationCityId === null || $items === []) {
            return [FlatRateShippingPrice::option($this->settings->shippingFlatRate())];
        }

        return $this->calculator->options(new ShippingPriceRequest(
            originCityId: $this->settings->shippingOriginCityId(),
            destinationCityId: $destinationCityId,
            parcels: $this->packer->pack($items, $this->boxes->specs()),
        ));
    }
}
