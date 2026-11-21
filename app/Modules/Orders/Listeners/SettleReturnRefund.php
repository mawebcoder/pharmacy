<?php

declare(strict_types=1);

namespace App\Modules\Orders\Listeners;

use App\Modules\Orders\Enums\OrderReturnStatus;
use App\Modules\Orders\Models\OrderReturn;
use App\Modules\Payments\Events\RefundApproved;

/**
 * A return's refund was approved in «بازگشت وجه‌ها» (owner, 2026-11-19): the return becomes «بازگشت وجه شد» with the
 * transfer reference.
 */
final class SettleReturnRefund
{
    public function handle(RefundApproved $event): void
    {
        if ($event->orderReturnId === null) {
            return;
        }

        $return = OrderReturn::query()->find($event->orderReturnId);

        if ($return instanceof OrderReturn && $return->getStatus() !== OrderReturnStatus::Refunded) {
            $return->setStatus(OrderReturnStatus::Refunded)->setRefundedAt(now())->setRefundReference($event->reference)->save();
        }
    }
}
