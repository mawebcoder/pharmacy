<?php

declare(strict_types=1);

namespace App\Modules\Payments\Actions;

use App\Modules\Payments\Enums\PaymentAttemptStatus;
use App\Modules\Payments\Gateways\Zarinpal\ZarinpalGateway;
use App\Modules\Payments\Models\Payment;

/**
 * «استعلام از زرین‌پال» on the admin order page (owner, 2026-11-18): asks Zarinpal again about a pending payment, for a
 * customer who paid but never came back. It goes through HandlePaymentCallback as an «OK» return, so it verifies with
 * the payment's own amount, locks the order's payments and marks the order paid exactly as the callback would. Paid,
 * failed and expired payments, and other gateways', are left as they are.
 */
final readonly class RecheckPayment
{
    public function __construct(private HandlePaymentCallback $handleCallback) {}

    public static function applies(Payment $payment): bool
    {
        return $payment->getGateway() === ZarinpalGateway::NAME && $payment->getStatus() === PaymentAttemptStatus::Pending;
    }

    public function __invoke(Payment $payment): PaymentAttemptStatus
    {
        if (self::applies($payment)) {
            $_ = ($this->handleCallback)(['Authority' => $payment->getAuthority(), 'Status' => 'OK']);
        }

        return $payment->refresh()->getStatus();
    }
}
