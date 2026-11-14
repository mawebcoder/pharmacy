<?php

declare(strict_types=1);

namespace App\Modules\Payments\Data;

/**
 * The gateway's verdict when the customer comes back: paid (with its reference and masked card) or not, and why.
 */
final readonly class PaymentVerificationData
{
    public function __construct(
        public bool $paid,
        public ?string $refId = null,
        public ?string $cardPan = null,
        public ?string $failureReason = null,
    ) {}
}
