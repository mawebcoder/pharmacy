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
 * Turns our ShippingPriceRequest into Tipax's `POST Pricing` body for one service (OmPriceDto, roadmap D6.4; adapter,
 * owner 2026-11-18): one packageInput per parcel, sizes from its box in cm, weight in kg (at least Tipax's minimum),
 * declared value in rial, the content by what it holds, and the codes from config/shipping.php. Tipax prices one
 * service per request (`serviceId` 0 is refused), so the strategy asks once per service on the route.
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
        private float $minWeightKg,
    ) {}

    /**
     * @return array{packageInputs: list<array<string, mixed>>, discountCode: null}
     */
    public function toBody(ShippingPriceRequest $request, int $serviceId): array
    {
        return [
            'packageInputs' => array_map(fn (PackedParcel $parcel): array => $this->package($request, $parcel, $serviceId), $request->parcels),
            'discountCode' => null,
        ];
    }

    /**
     * The parcel's Tipax content (owner, 2026-11-19): liquid or fragile wins, else the content its products share, else
     * the store's general content.
     */
    private function contentId(PackedParcel $parcel): int
    {
        if ($parcel->content === ParcelContent::LiquidFragile) {
            return $this->contentLiquidId;
        }

        return $parcel->tipaxContentId ?? $this->contentGeneralId;
    }

    /**
     * @return array<string, mixed>
     */
    private function package(ShippingPriceRequest $request, PackedParcel $parcel, int $serviceId): array
    {
        $box = $parcel->box;

        return [
            'origin' => ['cityId' => $request->originCityId],
            'destination' => ['cityId' => $request->destinationCityId],
            'weight' => max($this->minWeightKg, round($parcel->totalWeightG / 1000, 3)),
            'length' => $box instanceof BoxSpec ? $box->lengthCm : $this->unboxedSizeCm,
            'width' => $box instanceof BoxSpec ? $box->widthCm : $this->unboxedSizeCm,
            'height' => $box instanceof BoxSpec ? $box->heightCm : $this->unboxedSizeCm,
            'packageValue' => $parcel->declaredValue,
            'packingId' => $this->packingId,
            'packageContentId' => $this->contentId($parcel),
            'packType' => $this->packType,
            'serviceId' => $serviceId,
            'paymentType' => $this->paymentType->value,
            'pickupType' => ($request->isReturn ? TipaxPickupType::CollectedFromSender : $this->pickupType)->value,
            'distributionType' => ($request->isReturn ? TipaxDistributionType::ToReceiverAddress : $this->distributionType)->value,
        ];
    }
}
