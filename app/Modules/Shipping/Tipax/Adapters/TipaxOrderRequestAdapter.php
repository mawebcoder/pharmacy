<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Tipax\Adapters;

use App\Modules\Shipping\Data\BoxSpec;
use App\Modules\Shipping\Data\PackedParcel;
use App\Modules\Shipping\Data\TipaxRegistrationData;
use App\Modules\Shipping\Enums\ParcelContent;
use App\Modules\Shipping\Enums\TipaxDistributionType;
use App\Modules\Shipping\Enums\TipaxPaymentType;
use App\Modules\Shipping\Enums\TipaxPickupType;
use App\Modules\Shipping\Enums\TipaxShipmentKind;

/**
 * Turns a registration into Tipax's `POST OmOrder` body (OmV4OrderRegisterCuDto, roadmap S35; adapter, owner
 * 2026-11-19): one package per packed parcel with the same weight, sizes, value, packing and content rules as pricing,
 * the sender and receiver, our order number as traceCode, and the callback addresses Tipax requires. Going out, we drop
 * the parcels at a branch; a return is collected from the customer.
 */
final readonly class TipaxOrderRequestAdapter
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
     * @return array<string, mixed>
     */
    public function toBody(TipaxRegistrationData $registration, string $successUrl, string $failureUrl): array
    {
        $packages = [];

        foreach ($registration->parcels as $index => $parcel) {
            $packages[] = $this->package($registration, $parcel, $index + 1);
        }

        return [
            'packages' => $packages,
            'traceCode' => $registration->traceCode(),
            'successCallbackUrl' => $successUrl,
            'failureCallbackUrl' => $failureUrl,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function package(TipaxRegistrationData $registration, PackedParcel $parcel, int $sequence): array
    {
        $box = $parcel->box;
        $isReturn = $registration->kind === TipaxShipmentKind::Return;

        return array_filter([
            'origin' => $registration->sender->toTipax(),
            'destination' => $registration->receiver->toTipax(),
            'weight' => max($this->minWeightKg, round($parcel->totalWeightG / 1000, 3)),
            'length' => $box instanceof BoxSpec ? $box->lengthCm : $this->unboxedSizeCm,
            'width' => $box instanceof BoxSpec ? $box->widthCm : $this->unboxedSizeCm,
            'height' => $box instanceof BoxSpec ? $box->heightCm : $this->unboxedSizeCm,
            'packageValue' => $parcel->declaredValue,
            'packingId' => $this->packingId,
            'packageContentId' => $parcel->content === ParcelContent::LiquidFragile ? $this->contentLiquidId : ($parcel->tipaxContentId ?? $this->contentGeneralId),
            'packType' => $this->packType,
            'serviceId' => $registration->serviceId,
            'paymentType' => $this->paymentType->value,
            'pickupType' => ($isReturn ? TipaxPickupType::CollectedFromSender : $this->pickupType)->value,
            'distributionType' => ($isReturn ? TipaxDistributionType::ToReceiverAddress : $this->distributionType)->value,
            'cod' => 0,
            'enableLabelPrivacy' => false,
            'customParcelTitle' => $registration->traceCode().'-'.$sequence,
        ], fn (mixed $value): bool => $value !== null);
    }
}
