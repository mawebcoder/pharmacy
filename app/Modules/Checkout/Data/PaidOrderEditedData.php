<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Data;

/**
 * A paid order after staff edited it: its new total, what the customer now owes (texted a link for an hour), and what
 * they are owed (a refund record waiting for approval).
 */
final readonly class PaidOrderEditedData
{
    public function __construct(
        public int $total,
        public int $customerOwes,
        public int $refundOpened,
    ) {}
}
