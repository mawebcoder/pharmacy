<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Queries;

use App\Modules\Shipping\Enums\TipaxShipmentKind;
use App\Modules\Shipping\Enums\TipaxShipmentStatus;
use App\Modules\Shipping\Models\TipaxShipment;

/**
 * An order's Tipax shipments for the order pages (roadmap S35): whether it went out with Tipax, and each shipment's
 * codes and state.
 */
final readonly class TipaxShipments
{
    public function wasSent(int $orderId): bool
    {
        return TipaxShipment::query()
            ->where(TipaxShipment::COLUMN_ORDER_ID, $orderId)
            ->where(TipaxShipment::COLUMN_KIND, TipaxShipmentKind::Outbound)
            ->where(TipaxShipment::COLUMN_STATUS, '!=', TipaxShipmentStatus::Failed)
            ->exists();
    }

    public function hasReturn(int $orderId): bool
    {
        return TipaxShipment::query()
            ->where(TipaxShipment::COLUMN_ORDER_ID, $orderId)
            ->where(TipaxShipment::COLUMN_KIND, TipaxShipmentKind::Return)
            ->where(TipaxShipment::COLUMN_STATUS, '!=', TipaxShipmentStatus::Failed)
            ->exists();
    }

    /**
     * @return list<array{kind: string, status: string, tracking_codes: list<string>, failure_reason: string|null, registered_at: string|null}>
     */
    public function for(int $orderId): array
    {
        return array_values(TipaxShipment::query()
            ->where(TipaxShipment::COLUMN_ORDER_ID, $orderId)
            ->orderBy(TipaxShipment::COLUMN_ID)
            ->get()
            ->map(fn (TipaxShipment $shipment): array => [
                'kind' => $shipment->getKind()->label(),
                'status' => $shipment->getStatus()->label(),
                'tracking_codes' => $shipment->getTrackingCodes(),
                'failure_reason' => $shipment->getFailureReason(),
                'registered_at' => $shipment->getRegisteredAt()?->toIso8601String(),
            ])
            ->all());
    }
}
