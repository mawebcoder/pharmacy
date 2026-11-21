<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Enums\OrderReturnStatus;
use App\Modules\Orders\Models\OrderReturn;
use App\Modules\Shipping\Data\TipaxShipmentProgressData;
use App\Modules\Shipping\Exceptions\TipaxRequestFailed;
use App\Modules\Shipping\Queries\TipaxShipmentProgress;

/**
 * «استعلام از تیپاکس» on a return, and the nightly check (roadmap S27, owner 2026-11-19): keeps Tipax's status and moves
 * an approved return to «جمع‌آوری شد» once Tipax picked it up. Tipax reports parcels, not items: what arrived is
 * counted at the warehouse (ConfirmReturnReceived).
 */
final readonly class InquireOrderReturn
{
    public function __construct(private TipaxShipmentProgress $progress) {}

    /**
     * @throws TipaxRequestFailed
     */
    public function __invoke(OrderReturn $return): ?TipaxShipmentProgressData
    {
        if (! $return->needsPickup() || $return->getTrackingCodes() === []) {
            return null;
        }

        $progress = $this->progress->for($return->getNumber());

        if (! $progress instanceof TipaxShipmentProgressData) {
            return null;
        }

        $return->setTipaxStage($progress->stage)->setTipaxStatus($progress->status)->setTipaxCheckedAt(now());

        if ($progress->stage->isPickedUp() && $return->getPickedUpAt() === null) {
            $return->setPickedUpAt(now());
        }

        // «تحویل انبار» is set by staff counting what arrived (ConfirmReturnReceived), not by Tipax.
        if ($return->getStatus() === OrderReturnStatus::Approved && $progress->stage->isPickedUp()) {
            $return->setStatus(OrderReturnStatus::PickedUp);
        }

        $return->save();

        return $progress;
    }
}
