<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Queries;

use App\Modules\Shipping\Data\TipaxShipmentProgressData;
use App\Modules\Shipping\Enums\TipaxParcelStage;
use App\Modules\Shipping\Exceptions\TipaxRequestFailed;
use App\Modules\Shipping\Models\TipaxShipment;
use App\Modules\Shipping\Tipax\TipaxClient;

/**
 * Asks Tipax where a registration's parcels are (roadmap S27, owner 2026-11-19: a return may be cancelled only before
 * pickup and refunded only after it). A parcel Tipax has no record for yet is awaiting pickup.
 */
final readonly class TipaxShipmentProgress
{
    public function __construct(private TipaxClient $tipax) {}

    /**
     * @throws TipaxRequestFailed
     */
    public function for(string $traceCode): ?TipaxShipmentProgressData
    {
        $shipment = TipaxShipment::query()->where(TipaxShipment::COLUMN_TRACE_CODE, $traceCode)->first();

        if (! $shipment instanceof TipaxShipment || $shipment->getTrackingCodes() === []) {
            return null;
        }

        $parcels = $this->tipax->trackByCodes($shipment->getTrackingCodes());
        $stages = [];
        $titles = [];

        foreach ($shipment->getTrackingCodes() as $code) {
            $stages[] = TipaxParcelStage::fromStatusId($parcels[$code]['status_id'] ?? null);
            $titles[] = $parcels[$code]['status'] ?? 'ثبت اولیه';
        }

        return new TipaxShipmentProgressData(TipaxParcelStage::combined($stages), implode('، ', array_values(array_unique($titles))));
    }
}
