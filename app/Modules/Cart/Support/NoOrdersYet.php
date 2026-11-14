<?php

declare(strict_types=1);

namespace App\Modules\Cart\Support;

use App\Modules\Cart\Contracts\CustomerOrderHistory;

/**
 * The order history until the Orders module exists (S25 replaces this binding): no customer has ordered yet.
 */
final class NoOrdersYet implements CustomerOrderHistory
{
    public function hasPlacedOrder(int $customerId): bool
    {
        return false;
    }
}
