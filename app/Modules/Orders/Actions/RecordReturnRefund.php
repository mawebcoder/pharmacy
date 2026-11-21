<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Exceptions\OrderRefused;
use App\Modules\Orders\Models\OrderReturn;
use App\Modules\Payments\Actions\ApproveRefund;
use App\Modules\Payments\Exceptions\RefundRefused;
use App\Modules\Payments\Queries\OrderPayments;

/**
 * «ثبت بازگشت وجه» on a return's page (roadmap S27, owner 2026-11-19): approves the return's refund record in
 * «بازگشت وجه‌ها» with the bank transfer reference (ApproveRefund), which texts the customer and marks the return
 * refunded; only once the return is received at the warehouse (items never sent: once approved).
 */
final readonly class RecordReturnRefund
{
    public function __construct(
        private OrderPayments $payments,
        private ApproveRefund $approve,
    ) {}

    /**
     * @throws OrderRefused
     */
    public function __invoke(OrderReturn $return, string $reference, ?int $adminId): void
    {
        $refund = $this->payments->refundOfReturn($return->getId());

        if (! $return->isRefundable() || $refund === null) {
            throw OrderRefused::returnNotRefundable();
        }

        try {
            $this->approve->byId($refund['id'], $reference, (int) $adminId);
        } catch (RefundRefused $refused) {
            throw OrderRefused::returnNotRefundable();
        }
    }
}
