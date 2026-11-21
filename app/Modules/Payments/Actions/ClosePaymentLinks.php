<?php

declare(strict_types=1);

namespace App\Modules\Payments\Actions;

use App\Modules\Payments\Enums\PaymentAttemptStatus;
use App\Modules\Payments\Enums\PaymentKind;
use App\Modules\Payments\Models\Payment;

/**
 * An unpaid order was edited (owner, 2026-11-19): its open payment links are closed, so the old amount can no longer
 * be paid; a payment made on one anyway is not verified, and the gateway returns it. The next «پرداخت» opens a new
 * link for the new total.
 */
final class ClosePaymentLinks
{
    public function __invoke(int $orderId, string $reason): void
    {
        Payment::query()
            ->where(Payment::COLUMN_ORDER_ID, $orderId)
            ->where(Payment::COLUMN_STATUS, PaymentAttemptStatus::Pending)
            ->whereIn(Payment::COLUMN_KIND, [PaymentKind::Order, PaymentKind::Difference])
            ->lockForUpdate()
            ->get()
            ->each(fn (Payment $payment) => $payment->setStatus(PaymentAttemptStatus::Failed)->setFailureReason($reason)->setFailedAt(now())->save());
    }
}
