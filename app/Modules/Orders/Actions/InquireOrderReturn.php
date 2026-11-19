<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Enums\OrderReturnStatus;
use App\Modules\Orders\Models\OrderReturn;
use App\Modules\Shipping\Data\TipaxShipmentProgressData;
use App\Modules\Shipping\Enums\TipaxParcelStage;
use App\Modules\Shipping\Exceptions\TipaxRequestFailed;
use App\Modules\Shipping\Queries\TipaxShipmentProgress;

/**
 * «استعلام از تیپاکس» on a return, and the nightly check (roadmap S27, owner 2026-11-19): keeps Tipax's status and moves
 * the return forward only: picked up from the customer, then received at the warehouse. A refunded return keeps its
 * status; its Tipax status still follows.
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

        if ($progress->stage === TipaxParcelStage::Delivered && $return->getReceivedAt() === null) {
            $return->setReceivedAt(now());
        }

        $next = match (true) {
            $return->getStatus() === OrderReturnStatus::Approved && $progress->stage === TipaxParcelStage::Delivered => OrderReturnStatus::Received,
            $return->getStatus() === OrderReturnStatus::Approved && $progress->stage->isPickedUp() => OrderReturnStatus::PickedUp,
            $return->getStatus() === OrderReturnStatus::PickedUp && $progress->stage === TipaxParcelStage::Delivered => OrderReturnStatus::Received,
            default => $return->getStatus(),
        };
        $return->setStatus($next)->save();

        return $progress;
    }
}
