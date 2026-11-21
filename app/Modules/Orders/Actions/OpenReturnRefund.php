<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Models\OrderReturn;
use App\Modules\Orders\Models\OrderReturnItem;
use App\Modules\Payments\Actions\CreateRefund;
use App\Modules\Payments\Enums\RefundSource;
use App\Modules\Payments\Queries\OrderPayments;

/**
 * A return ready to be refunded (owner, 2026-11-19: received at the warehouse, or approved when its items were never
 * sent) gets its refund record in «بازگشت وجه‌ها», waiting for approval, once; nothing when nothing is refunded.
 */
final readonly class OpenReturnRefund
{
    public function __construct(
        private CreateRefund $createRefund,
        private OrderPayments $payments,
    ) {}

    public function __invoke(OrderReturn $return, ?int $adminId): void
    {
        if ($return->getRefundTotal() <= 0 || $this->payments->refundOfReturn($return->getId()) !== null) {
            return;
        }

        $reasons = $return->items->map(fn (OrderReturnItem $row): string => $row->getReason()->label())->unique()->implode('، ');

        ($this->createRefund)(
            $return->getOrderId(),
            $return->getRefundTotal(),
            RefundSource::OrderReturn,
            'مرجوعی '.$return->getNumber().($reasons === '' ? '' : ' ('.$reasons.')'),
            $return->getId(),
            $adminId,
        );
    }
}
