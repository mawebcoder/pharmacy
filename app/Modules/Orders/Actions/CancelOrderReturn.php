<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Enums\OrderReturnStatus;
use App\Modules\Orders\Exceptions\OrderRefused;
use App\Modules\Orders\Models\OrderReturn;
use App\Modules\Shipping\Actions\CancelTipaxShipment;
use App\Modules\Shipping\Exceptions\TipaxRequestFailed;

/**
 * «لغو مرجوعی» (roadmap S27, owner 2026-11-19): only before Tipax picks it up. An approved return is checked with Tipax
 * and cancelled there first; once picked up, or when Tipax refuses, it stays. Its units may then be returned again, and the other drafts' refunds are
 * worked out again.
 */
final readonly class CancelOrderReturn
{
    public function __construct(
        private CancelTipaxShipment $cancelAtTipax,
        private InquireOrderReturn $inquire,
        private RecalculateReturnRefunds $recalculate,
    ) {}

    /**
     * @throws OrderRefused
     */
    public function __invoke(OrderReturn $return, ?string $reason, ?int $adminId): void
    {
        if ($return->getStatus() === OrderReturnStatus::Cancelled) {
            throw OrderRefused::returnAlreadyCancelled();
        }

        if (! in_array($return->getStatus(), [OrderReturnStatus::Draft, OrderReturnStatus::Approved], true)) {
            throw OrderRefused::returnPickedUp();
        }

        if ($return->getStatus() === OrderReturnStatus::Approved && $return->needsPickup()) {
            // Ask Tipax first: once the parcels are picked up the return stays (owner, 2026-11-19).
            try {
                ($this->inquire)($return);
            } catch (TipaxRequestFailed) {
                // Tipax's own cancel below refuses a parcel already picked up.
            }

            if ($return->getTipaxStage()?->isPickedUp() === true) {
                throw OrderRefused::returnPickedUp();
            }

            try {
                ($this->cancelAtTipax)($return->getNumber());
            } catch (TipaxRequestFailed $failed) {
                throw OrderRefused::carrierCancelRefused($failed->getMessage());
            }
        }

        $reason = trim((string) $reason);
        $return->setStatus(OrderReturnStatus::Cancelled)
            ->setCancelReason($reason === '' ? null : $reason)
            ->setCancelledBy($adminId)
            ->setCancelledAt(now())
            ->save();

        ($this->recalculate)($return->getOrder());
    }
}
