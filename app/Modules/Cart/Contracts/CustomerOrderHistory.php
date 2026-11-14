<?php

declare(strict_types=1);

namespace App\Modules\Cart\Contracts;

/**
 * Whether a customer has ordered before, for «first order only» coupons (roadmap D3.4). Orders arrive with S25,
 * which binds its own implementation; until then nobody has an order (NoOrdersYet).
 */
interface CustomerOrderHistory
{
    public function hasPlacedOrder(int $customerId): bool;
}
