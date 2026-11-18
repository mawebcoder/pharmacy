<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Tipax\Adapters;

use App\Modules\Shipping\Data\BoxSpec;
use App\Modules\Shipping\Data\PackedParcel;
use App\Modules\Shipping\Data\ShippingPriceRequest;
use App\Modules\Shipping\Enums\ParcelContent;
use App\Modules\Shipping\Enums\TipaxDistributionType;
use App\Modules\Shipping\Enums\TipaxPaymentType;
use App\Modules\Shipping\Enums\TipaxPickupType;

/**
 * Turns our ShippingPriceRequest into Tipax's `POST Pricing` body (OmPriceDto, roadmap D6.4; adapter, owner
 * 2026-11-18): one packageInput per parcel, sizes from its box in cm, weight in kg, declared value in rial, the content
 * by what it holds, and the codes from config/shipping.php. `serviceId` 0 asks for every service on the route.
 */
final readonly class TipaxPriceRequestAdapter
{
    public function __construct(
        private TipaxPaymentType $paymentType,
        private TipaxPickupType $pickupType,
        private TipaxDistributionType $distributionType,
        private int $packingId,
        private int $packType,
        private int $contentGeneralId,
        private int $contentLiquidId,
        private int $unboxedSizeCm,
    ) {}

    /**
     * @return array{packageInputs: list<array<string, mixed>>, discountCode: null}
     */
    public function toBody(ShippingPriceRequest $request): array
    {
        return [
            'packageInputs' => array_map(fn (PackedParcel $parcel): array => $this->package($request, $parcel), $request->parcels),
            'discountCode' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function package(ShippingPriceRequest $request, PackedParcel $parcel): array
    {
        $box = $parcel->box;

        return [
            'origin' => ['cityId' => $request->originCityId],
            'destination' => ['cityId' => $request->destinationCityId],
            'weight' => round($parcel->totalWeightG / 1000, 3),
            'length' => $box instanceof BoxSpec ? $box->lengthCm : $this->unboxedSizeCm,
            'width' => $box instanceof BoxSpec ? $box->widthCm : $this->unboxedSizeCm,
            'height' => $box instanceof BoxSpec ? $box->heightCm : $this->unboxedSizeCm,
            'packageValue' => $parcel->declaredValue,
            'packingId' => $this->packingId,
            'packageContentId' => $parcel->content === ParcelContent::LiquidFragile ? $this->contentLiquidId : $this->contentGeneralId,
            'packType' => $this->packType,
            'serviceId' => 0,
            'paymentType' => $this->paymentType->value,
            'pickupType' => $this->pickupType->value,
            'distributionType' => $this->distributionType->value,
        ];
    }
}
