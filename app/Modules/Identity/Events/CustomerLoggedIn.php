<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

/**
 * A customer signed in on the storefront (roadmap S19); the cart (S21) listens to merge the guest cart into theirs.
 */
final readonly class CustomerLoggedIn
{
    public function __construct(
        public int $customerId,
        public bool $isNewCustomer,
    ) {}
}
