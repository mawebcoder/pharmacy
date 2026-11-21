<?php

declare(strict_types=1);

namespace App\Modules\Payments\Events;

use App\Modules\Payments\Enums\RefundSource;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Staff approved a refund: the money was transferred to the customer (owner, 2026-11-19). The customer is texted; a
 * return's refund marks the return refunded.
 */
final readonly class RefundApproved implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  array{items?: int, shipping?: int}  $details  a cancelled order's split
     */
    public function __construct(
        public int $paymentId,
        public int $orderId,
        public ?int $orderReturnId,
        public int $amount,
        public string $reference,
        public ?RefundSource $source = null,
        public array $details = [],
    ) {}
}
