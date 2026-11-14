<?php

declare(strict_types=1);

namespace App\Modules\Identity\Policies;

use App\Modules\Identity\Models\Address;
use App\Modules\Identity\Models\Customer;

/**
 * Addresses are visible only to their owner (roadmap D16 privacy). Staff see them read-only on the customer's admin
 * page, through the customer policy.
 */
final class AddressPolicy
{
    public function view(Customer $customer, Address $address): bool
    {
        return $address->getCustomerId() === $customer->getId();
    }

    public function update(Customer $customer, Address $address): bool
    {
        return $address->getCustomerId() === $customer->getId();
    }

    public function delete(Customer $customer, Address $address): bool
    {
        return $address->getCustomerId() === $customer->getId();
    }
}
