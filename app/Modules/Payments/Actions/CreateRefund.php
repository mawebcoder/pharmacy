<?php

declare(strict_types=1);

namespace App\Modules\Payments\Actions;

use App\Modules\Payments\Enums\PaymentAttemptStatus;
use App\Modules\Payments\Enums\PaymentKind;
use App\Modules\Payments\Enums\RefundSource;
use App\Modules\Payments\Exceptions\RefundRefused;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Queries\OrderPayments;
use Illuminate\Support\Str;

/**
 * Opens a refund record waiting for approval (owner, 2026-11-19): from an order edit that lowered a paid order's total,
 * or a return received at the warehouse (one per return). It never asks more than the order still has paid (paid
 * payments less refunds approved or waiting).
 */
final readonly class CreateRefund
{
    public function __construct(private OrderPayments $payments) {}

    /**
     * @param  array{items?: int, shipping?: int}  $details  a cancelled order's split
     *
     * @throws RefundRefused
     */
    public function __invoke(int $orderId, int $amount, RefundSource $source, string $reason, ?int $orderReturnId = null, ?int $adminId = null, array $details = []): Payment
    {
        if ($amount <= 0) {
            throw RefundRefused::empty();
        }

        if ($orderReturnId !== null && Payment::query()->where(Payment::COLUMN_ORDER_RETURN_ID, $orderReturnId)->exists()) {
            throw RefundRefused::alreadyOpened();
        }

        $refundable = $this->payments->refundable($orderId);

        if ($amount > $refundable) {
            throw RefundRefused::tooLarge($refundable);
        }

        $refund = (new Payment)
            ->setOrderId($orderId)
            ->setKind(PaymentKind::Refund)
            ->setRefundSource($source)
            ->setOrderReturnId($orderReturnId)
            ->setGateway(Payment::MANUAL_GATEWAY)
            ->setAmount($amount)
            ->setAuthority('refund-'.Str::uuid()->toString())
            ->setUrl('')
            ->setStatus(PaymentAttemptStatus::Pending)
            ->setExpiresAt(now())
            ->setReason($reason)
            ->setDetails($details)
            ->setCreatedBy($adminId);
        $refund->save();

        return $refund;
    }
}
