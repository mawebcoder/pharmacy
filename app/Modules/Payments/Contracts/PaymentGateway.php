<?php

declare(strict_types=1);

namespace App\Modules\Payments\Contracts;

use App\Modules\Payments\Data\PaymentLinkData;
use App\Modules\Payments\Data\PaymentRequestData;
use App\Modules\Payments\Data\PaymentVerificationData;
use App\Modules\Payments\Exceptions\GatewayUnavailable;
use App\Modules\Payments\Models\Payment;

/**
 * A payment gateway (roadmap D7.6). S23 ships FakePaymentGateway (local and staging); S36 adds Zarinpal behind the
 * same contract. All trust comes from verify(), never from the callback's query string alone.
 */
interface PaymentGateway
{
    public function name(): string;

    /**
     * @throws GatewayUnavailable
     */
    public function request(PaymentRequestData $request): PaymentLinkData;

    /**
     * @param  array<string, mixed>  $query  the callback's query string
     */
    public function verify(Payment $payment, array $query): PaymentVerificationData;
}
