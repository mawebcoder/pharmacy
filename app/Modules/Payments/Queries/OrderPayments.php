<?php

declare(strict_types=1);

namespace App\Modules\Payments\Queries;

use App\Modules\Payments\Enums\PaymentAttemptStatus;
use App\Modules\Payments\Models\Payment;

/**
 * An order's payment attempts as other modules need them (roadmap S25): why the last one failed, and its reference.
 */
final readonly class OrderPayments
{
    public function lastFailureReason(int $orderId): ?string
    {
        $last = Payment::query()->where(Payment::COLUMN_ORDER_ID, $orderId)->latest(Payment::COLUMN_ID)->first();

        return $last instanceof Payment && $last->getStatus() === PaymentAttemptStatus::Failed ? $last->getFailureReason() : null;
    }

    public function paidReference(int $orderId): ?string
    {
        return Payment::query()
            ->where(Payment::COLUMN_ORDER_ID, $orderId)
            ->where(Payment::COLUMN_STATUS, PaymentAttemptStatus::Paid)
            ->latest(Payment::COLUMN_ID)
            ->first()
            ?->getRefId();
    }
}
