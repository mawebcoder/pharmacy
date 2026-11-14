<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Data\AddressData;
use App\Modules\Identity\Exceptions\AddressRefused;
use App\Modules\Identity\Models\Address;
use App\Modules\Identity\Models\Customer;
use Illuminate\Support\Facades\DB;

/**
 * Adds or edits a customer's address (roadmap D3.3, S18). A customer keeps at most 10; their first address, or one
 * saved as default, becomes the only default.
 */
final class SaveAddress
{
    /**
     * @throws AddressRefused
     */
    #[\NoDiscard]
    public function __invoke(Customer $customer, AddressData $data, ?Address $address = null): Address
    {
        if ($address instanceof Address && $address->getCustomerId() !== $customer->getId()) {
            throw AddressRefused::notYours();
        }

        return DB::transaction(function () use ($customer, $data, $address): Address {
            $existing = Address::query()->ownedBy($customer->getId())->lockForUpdate()->count();

            if (! $address instanceof Address && $existing >= Address::MAX_PER_CUSTOMER) {
                throw AddressRefused::tooMany(Address::MAX_PER_CUSTOMER);
            }

            $address ??= (new Address)->setCustomerId($customer->getId());
            $makeDefault = $data->isDefault || $existing === 0 || ($address->exists && $address->isDefault());

            $address
                ->setTitle($data->title)
                ->setReceiverName($data->receiverName)
                ->setReceiverMobile($data->receiverMobile)
                ->setProvinceId($data->provinceId)
                ->setCityId($data->cityId)
                ->setFullAddress($data->fullAddress)
                ->setPlaque($data->plaque)
                ->setUnit($data->unit)
                ->setFloor($data->floor)
                ->setPostalCode($data->postalCode)
                ->setIsDefault($makeDefault)
                ->save();

            if ($makeDefault) {
                self::clearOtherDefaults($customer->getId(), $address->getId());
            }

            return $address;
        });
    }

    public static function clearOtherDefaults(int $customerId, int $defaultAddressId): void
    {
        Address::query()
            ->ownedBy($customerId)
            ->whereKeyNot($defaultAddressId)
            ->where(Address::COLUMN_IS_DEFAULT, true)
            ->update([Address::COLUMN_IS_DEFAULT => false]);
    }
}
