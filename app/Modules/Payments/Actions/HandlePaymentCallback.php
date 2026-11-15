<?php

declare(strict_types=1);

namespace App\Modules\Payments\Actions;

use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Enums\PaymentAttemptStatus;
use App\Modules\Payments\Events\PaymentSucceeded;
use App\Modules\Payments\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * The customer is back from the gateway (roadmap D7.2, D7.5). The order's attempts are locked together and this one is
 * verified with the gateway; a verified one becomes paid and PaymentSucceeded is sent (once, even if the callback
 * repeats); otherwise it fails and the order stays payable until it expires. An order has at most one successful
 * payment (owner, 2026-11-15): when another attempt is already paid this one is not verified, so the gateway returns
 * the money, and it is marked failed. Returns the order id, or null for an unknown authority.
 */
final readonly class HandlePaymentCallback
{
    public const string ALREADY_PAID = 'سفارش قبلاً پرداخت شده است.';

    public function __construct(private PaymentGateway $gateway) {}

    /**
     * @param  array<string, mixed>  $query
     */
    #[\NoDiscard]
    public function __invoke(array $query): ?int
    {
        $authority = (string) ($query['Authority'] ?? '');

        if ($authority === '') {
            return null;
        }

        $orderId = Payment::query()->where(Payment::COLUMN_AUTHORITY, $authority)->value(Payment::COLUMN_ORDER_ID);

        if ($orderId === null) {
            return null;
        }

        return DB::transaction(function () use ($authority, $query, $orderId): int {
            // Every attempt of the order, locked in one go, so two callbacks of one order never both succeed.
            $attempts = Payment::query()->where(Payment::COLUMN_ORDER_ID, $orderId)->orderBy(Payment::COLUMN_ID)->lockForUpdate()->get();
            /** @var Payment $payment */
            $payment = $attempts->first(fn (Payment $attempt): bool => $attempt->getAuthority() === $authority);

            if ($payment->getStatus() !== PaymentAttemptStatus::Pending) {
                return $payment->getOrderId();
            }

            if ($attempts->contains(fn (Payment $attempt): bool => $attempt->getStatus() === PaymentAttemptStatus::Paid)) {
                $payment->setStatus(PaymentAttemptStatus::Failed)
                    ->setFailureReason(self::ALREADY_PAID)
                    ->setFailedAt(now())
                    ->save();

                return $payment->getOrderId();
            }

            $verification = $this->gateway->verify($payment, $query);

            if ($verification->paid) {
                $payment->setStatus(PaymentAttemptStatus::Paid)
                    ->setRefId($verification->refId)
                    ->setCardPan($verification->cardPan)
                    ->setVerifiedAt(now())
                    ->save();

                event(new PaymentSucceeded($payment->getOrderId(), $payment->getId()));
            } else {
                $payment->setStatus(PaymentAttemptStatus::Failed)
                    ->setFailureReason($verification->failureReason)
                    ->setFailedAt(now())
                    ->save();
            }

            return $payment->getOrderId();
        });
    }
}
