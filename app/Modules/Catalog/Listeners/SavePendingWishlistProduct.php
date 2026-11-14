<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Listeners;

use App\Modules\Catalog\Actions\AddToWishlist;
use App\Modules\Catalog\Support\PendingWishlist;
use App\Modules\Identity\Events\CustomerLoggedIn;

/**
 * A guest who pressed a heart was sent to sign in first (roadmap S18); once signed in, that product is saved.
 */
final readonly class SavePendingWishlistProduct
{
    public function __construct(private AddToWishlist $addToWishlist) {}

    public function handle(CustomerLoggedIn $event): void
    {
        $productId = PendingWishlist::pull();

        if ($productId !== null) {
            $saved = ($this->addToWishlist)($event->customerId, $productId);

            if ($saved) {
                session()->flash(PendingWishlist::SAVED_FLASH, true);
            }
        }
    }
}
