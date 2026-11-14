<?php

declare(strict_types=1);

namespace App\Modules\Payments\Data;

/**
 * A gateway's answer to a payment request: its reference (authority) and where to send the customer.
 */
final readonly class PaymentLinkData
{
    public function __construct(
        public string $authority,
        public string $url,
    ) {}
}
