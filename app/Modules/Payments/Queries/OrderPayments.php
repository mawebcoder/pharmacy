<?php

declare(strict_types=1);

namespace App\Modules\Payments\Queries;

use App\Modules\Payments\Enums\PaymentAttemptStatus;
use App\Modules\Payments\Enums\PaymentKind;
use App\Modules\Payments\Models\Payment;

/**
 * An order's payment attempts as other modules need them (roadmap S23): why the last one failed, and its reference.
 */
final readonly class OrderPayments
{
    public function lastFailureReason(int $orderId): ?string
    {
        $last = Payment::query()->where(Payment::COLUMN_ORDER_ID, $orderId)->where(Payment::COLUMN_KIND, '!=', PaymentKind::Refund)->latest(Payment::COLUMN_ID)->first();

        return $last instanceof Payment && $last->getStatus() === PaymentAttemptStatus::Failed ? $last->getFailureReason() : null;
    }

    public function paidReference(int $orderId): ?string
    {
        return Payment::query()
            ->where(Payment::COLUMN_ORDER_ID, $orderId)
            ->where(Payment::COLUMN_KIND, PaymentKind::Order)
            ->where(Payment::COLUMN_STATUS, PaymentAttemptStatus::Paid)
            ->latest(Payment::COLUMN_ID)
            ->first()
            ?->getRefId();
    }

    /**
     * The card the customer paid the order with (masked), where a refund goes back to.
     */
    public function paidCard(int $orderId): ?string
    {
        return Payment::query()
            ->where(Payment::COLUMN_ORDER_ID, $orderId)
            ->where(Payment::COLUMN_KIND, PaymentKind::Order)
            ->where(Payment::COLUMN_STATUS, PaymentAttemptStatus::Paid)
            ->value(Payment::COLUMN_CARD_PAN);
    }

    /**
     * What the order still has paid (owner, 2026-11-19): paid order and difference payments, less refunds approved or
     * waiting for approval.
     */
    public function refundable(int $orderId): int
    {
        $balance = $this->balance($orderId);

        return max(0, $balance['paid'] - $balance['refunded'] - $balance['refund_waiting']);
    }

    /**
     * The order's money (owner, 2026-11-19): paid (order and differences), refunded (approved), refunds waiting for
     * approval.
     *
     * @return array{paid: int, refunded: int, refund_waiting: int}
     */
    public function balance(int $orderId): array
    {
        $rows = Payment::query()->where(Payment::COLUMN_ORDER_ID, $orderId)->get();
        $sum = fn (callable $match): int => (int) $rows->filter($match)->sum(fn (Payment $payment): int => $payment->getAmount());

        return [
            'paid' => $sum(fn (Payment $payment): bool => $payment->getKind() !== PaymentKind::Refund && $payment->getStatus() === PaymentAttemptStatus::Paid),
            'refunded' => $sum(fn (Payment $payment): bool => $payment->getKind() === PaymentKind::Refund && $payment->getStatus() === PaymentAttemptStatus::Paid),
            'refund_waiting' => $sum(fn (Payment $payment): bool => $payment->getKind() === PaymentKind::Refund && $payment->getStatus() === PaymentAttemptStatus::Pending),
        ];
    }

    /**
     * The order's refund records, newest first, for links from the order page.
     *
     * @return list<array{id: int, amount: int, status: string, source: string|null}>
     */
    public function refunds(int $orderId): array
    {
        return array_values(Payment::query()
            ->where(Payment::COLUMN_ORDER_ID, $orderId)
            ->where(Payment::COLUMN_KIND, PaymentKind::Refund)
            ->latest(Payment::COLUMN_ID)
            ->get()
            ->map(fn (Payment $refund): array => [
                'id' => $refund->getId(),
                'amount' => $refund->getAmount(),
                'status' => $refund->statusLabel(),
                'source' => $refund->getRefundSource()?->label(),
            ])
            ->all());
    }

    /**
     * A return's refund record, for the link from the return's page.
     *
     * @return array{id: int, amount: int, status: string}|null
     */
    public function refundOfReturn(int $orderReturnId): ?array
    {
        $refund = Payment::query()->where(Payment::COLUMN_ORDER_RETURN_ID, $orderReturnId)->first();

        return $refund instanceof Payment ? ['id' => $refund->getId(), 'amount' => $refund->getAmount(), 'status' => $refund->statusLabel()] : null;
    }
}
