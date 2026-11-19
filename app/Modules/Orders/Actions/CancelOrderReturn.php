<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Enums\OrderReturnStatus;
use App\Modules\Orders\Exceptions\OrderRefused;
use App\Modules\Orders\Models\OrderReturn;
use App\Modules\Shipping\Actions\CancelTipaxShipment;
use App\Modules\Shipping\Exceptions\TipaxRequestFailed;

/**
 * «لغو مرجوعی» (roadmap S27, owner 2026-11-19): an approved return is cancelled at Tipax first; when Tipax refuses
 * (e.g. already picked up) it stays approved. Its units may then be returned again, and the other drafts' refunds are
 * worked out again.
 */
final readonly class CancelOrderReturn
{
    public function __construct(
        private CancelTipaxShipment $cancelAtTipax,
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

        if ($return->getStatus() === OrderReturnStatus::Approved) {
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
