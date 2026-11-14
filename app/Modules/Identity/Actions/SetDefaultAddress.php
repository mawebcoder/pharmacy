<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Exceptions\AddressRefused;
use App\Modules\Identity\Models\Address;
use App\Modules\Identity\Models\Customer;
use Illuminate\Support\Facades\DB;

/**
 * Makes one of the customer's addresses their default (roadmap S18); the others stop being default.
 */
final class SetDefaultAddress
{
    /**
     * @throws AddressRefused
     */
    public function __invoke(Customer $customer, Address $address): void
    {
        if ($address->getCustomerId() !== $customer->getId()) {
            throw AddressRefused::notYours();
        }

        DB::transaction(function () use ($customer, $address): void {
            $address->setIsDefault(true)->save();
            SaveAddress::clearOtherDefaults($customer->getId(), $address->getId());
        });
    }
}
