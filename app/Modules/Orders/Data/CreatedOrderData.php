<?php

declare(strict_types=1);

namespace App\Modules\Orders\Data;

use Illuminate\Support\Carbon;

/**
 * An order CreateOrder just saved: what the Checkout module needs to open its payment link.
 */
final readonly class CreatedOrderData
{
    public function __construct(
        public int $orderId,
        public string $number,
        public int $total,
        public Carbon $expiresAt,
    ) {}
}
