<?php

declare(strict_types=1);

namespace App\Modules\Payments\Actions;

use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Enums\PaymentAttemptStatus;
use App\Modules\Payments\Events\PaymentSucceeded;
use App\Modules\Payments\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * The customer is back from the gateway (roadmap D7.2, D7.5). The attempt is locked and verified with the gateway;
 * a verified one becomes paid and PaymentSucceeded is sent (once, even if the callback repeats); otherwise it fails
 * and the order stays payable until it expires. Returns the order id, or null for an unknown authority.
 */
final readonly class HandlePaymentCallback
{
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

        return DB::transaction(function () use ($authority, $query): ?int {
            $payment = Payment::query()->where(Payment::COLUMN_AUTHORITY, $authority)->lockForUpdate()->first();

            if (! $payment instanceof Payment) {
                return null;
            }

            if ($payment->getStatus() !== PaymentAttemptStatus::Pending) {
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
