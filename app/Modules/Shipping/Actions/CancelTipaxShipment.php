<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Actions;

use App\Modules\Shipping\Enums\TipaxShipmentStatus;
use App\Modules\Shipping\Exceptions\TipaxRequestFailed;
use App\Modules\Shipping\Models\TipaxShipment;
use App\Modules\Shipping\Tipax\TipaxClient;

/**
 * Cancels a registration at Tipax (roadmap S27, owner 2026-11-19: cancelling an approved return), parcel by parcel.
 * When Tipax refuses (e.g. already picked up) the registration stays as it was and Tipax's reason is thrown.
 */
final readonly class CancelTipaxShipment
{
    public function __construct(private TipaxClient $tipax) {}

    /**
     * @throws TipaxRequestFailed
     */
    public function __invoke(string $traceCode): void
    {
        $shipment = TipaxShipment::query()->where(TipaxShipment::COLUMN_TRACE_CODE, $traceCode)->first();

        if (! $shipment instanceof TipaxShipment || ! in_array($shipment->getStatus(), [TipaxShipmentStatus::Registered, TipaxShipmentStatus::Confirmed], true)) {
            return;
        }

        foreach ($shipment->getTrackingCodes() as $trackingCode) {
            $this->tipax->cancelParcel($trackingCode);
        }

        $shipment->setStatus(TipaxShipmentStatus::Cancelled)->save();
    }
}
