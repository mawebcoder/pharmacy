<?php

declare(strict_types=1);

namespace App\Modules\Cart\Data;

use App\Modules\Cart\Support\CartCookie;

/**
 * Whose cart: the signed-in customer, else the guest's cookie token (null when the guest has no cart yet).
 */
final readonly class CartOwnerData
{
    public function __construct(
        public ?int $customerId,
        public ?string $guestToken,
    ) {}

    /**
     * The shopper of the current request. Asks for the user, not just the session's id, so a deleted account counts
     * as a guest.
     */
    public static function current(): self
    {
        $customerId = auth('customer')->user()?->getAuthIdentifier();

        return $customerId === null
            ? new self(null, CartCookie::token())
            : new self((int) $customerId, null);
    }

    public function isGuest(): bool
    {
        return $this->customerId === null;
    }
}
