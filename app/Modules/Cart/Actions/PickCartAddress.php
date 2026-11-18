<?php

declare(strict_types=1);

namespace App\Modules\Cart\Actions;

use App\Modules\Cart\Data\CartOwnerData;
use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Queries\CurrentCart;

/**
 * Remembers which of the customer's addresses this order goes to (roadmap S23, checkout address step). The caller
 * checks the address is theirs (Identity's CustomerAddresses). Nothing happens without a cart.
 */
final readonly class PickCartAddress
{
    public function __construct(private CurrentCart $carts) {}

    public function __invoke(int $customerId, int $addressId): void
    {
        $cart = $this->carts->find(new CartOwnerData($customerId, null));

        if ($cart instanceof Cart) {
            $cart->setAddressId($addressId)->save();
        }
    }
}
