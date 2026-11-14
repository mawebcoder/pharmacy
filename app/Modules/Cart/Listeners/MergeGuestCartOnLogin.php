<?php

declare(strict_types=1);

namespace App\Modules\Cart\Listeners;

use App\Modules\Cart\Actions\MergeGuestCart;
use App\Modules\Cart\Support\CartCookie;
use App\Modules\Identity\Events\CustomerLoggedIn;

/**
 * A guest's cart follows them into their account when they sign in (roadmap D13.3, S19).
 */
final readonly class MergeGuestCartOnLogin
{
    public function __construct(private MergeGuestCart $mergeGuestCart) {}

    public function handle(CustomerLoggedIn $event): void
    {
        $token = CartCookie::token();

        if ($token !== null) {
            ($this->mergeGuestCart)($token, $event->customerId);
        }
    }
}
