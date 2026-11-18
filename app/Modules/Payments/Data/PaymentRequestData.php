<?php

declare(strict_types=1);

namespace App\Modules\Payments\Data;

use Illuminate\Support\Carbon;

/**
 * What a payment link is opened for (roadmap D7, S23): the order, the amount in rial, a description and the payer's
 * mobile for the gateway, and when the order stops accepting payment.
 */
final readonly class PaymentRequestData
{
    public function __construct(
        public int $orderId,
        public int $amount,
        public string $description,
        public string $mobile,
        public Carbon $expiresAt,
    ) {}
}
