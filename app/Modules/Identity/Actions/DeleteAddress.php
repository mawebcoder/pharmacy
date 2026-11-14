<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Exceptions\AddressRefused;
use App\Modules\Identity\Models\Address;
use App\Modules\Identity\Models\Customer;
use Illuminate\Support\Facades\DB;

/**
 * Removes one of the customer's addresses (soft delete, so orders keep their address id; roadmap S18). When it was
 * the default, the newest remaining address becomes the default.
 */
final class DeleteAddress
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
            $wasDefault = $address->isDefault();
            $address->setIsDefault(false)->save();
            $address->delete();

            if ($wasDefault) {
                Address::query()
                    ->ownedBy($customer->getId())
                    ->latest(Address::COLUMN_ID)
                    ->first()
                    ?->setIsDefault(true)
                    ->save();
            }
        });
    }
}
