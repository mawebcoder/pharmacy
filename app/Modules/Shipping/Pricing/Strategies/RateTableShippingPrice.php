<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Pricing\Strategies;

use App\Modules\Locations\Queries\DeliveryLocations;
use App\Modules\Shipping\Contracts\ShippingPriceStrategy;
use App\Modules\Shipping\Data\ShippingOptionData;
use App\Modules\Shipping\Data\ShippingPriceRequest;
use App\Modules\Shipping\Enums\ShippingDistance;
use App\Modules\Shipping\Models\ShippingFallbackRate;

/**
 * «نرخ ارسال جایگزین» as a strategy (roadmap S31, owner 2026-11-19): the fallback when Tipax cannot price an order.
 * Each parcel pays the price of the lightest band that takes its weight (the heaviest band when none does) for its
 * distance from the warehouse, and the order pays the sum. With no rows it does not apply and the flat rate stands in.
 * An unknown warehouse city counts as another province.
 */
final readonly class RateTableShippingPrice implements ShippingPriceStrategy
{
    public const string KEY = 'fallback_table';

    public function __construct(private DeliveryLocations $locations) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function supports(ShippingPriceRequest $request): bool
    {
        return $request->parcels !== [] && ShippingFallbackRate::query()->exists();
    }

    public function options(ShippingPriceRequest $request): array
    {
        $bands = ShippingFallbackRate::query()->orderBy(ShippingFallbackRate::COLUMN_MAX_WEIGHT_G)->get()->all();

        if ($bands === []) {
            return [];
        }

        $heaviest = $bands[array_key_last($bands)];
        $distance = $this->distance($request->originCityId, $request->destinationCityId);
        $price = 0;
        $usedBands = [];

        foreach ($request->parcels as $parcel) {
            $band = array_find($bands, fn (ShippingFallbackRate $rate): bool => $rate->getMaxWeightG() >= $parcel->totalWeightG) ?? $heaviest;
            $price += $band->priceFor($distance);
            $usedBands[] = $band->getMaxWeightG();
        }

        return [new ShippingOptionData(
            key: self::KEY,
            method: self::KEY,
            title: 'ارسال',
            price: $price,
            deliveryDays: null,
            isEstimate: true,
            details: ['distance' => $distance->value, 'bands_g' => $usedBands],
        )];
    }

    private function distance(?int $originCityId, int $destinationCityId): ShippingDistance
    {
        if ($originCityId === null) {
            return ShippingDistance::OtherProvince;
        }

        if ($originCityId === $destinationCityId) {
            return ShippingDistance::SameCity;
        }

        $origin = $this->locations->place($originCityId);
        $destination = $this->locations->place($destinationCityId);

        return $origin !== null && $destination !== null && $origin['province_id'] === $destination['province_id']
            ? ShippingDistance::SameProvince
            : ShippingDistance::OtherProvince;
    }
}
