<?php

declare(strict_types=1);

namespace App\Modules\Identity\Queries;

use App\Modules\Identity\Data\AddressSnapshotData;
use App\Modules\Identity\Models\Address;
use App\Modules\Locations\Queries\DeliveryLocations;

/**
 * A customer's addresses for checkout (roadmap S25), read by other modules: which address an order goes to (the one
 * picked on the address step if it is still theirs, else their default, else their newest) and its snapshot.
 */
final readonly class CustomerAddresses
{
    public function __construct(private DeliveryLocations $locations) {}

    public function hasAny(int $customerId): bool
    {
        return Address::query()->ownedBy($customerId)->exists();
    }

    /**
     * The address the order goes to: the picked one when it still belongs to the customer, else the default.
     */
    public function chosenId(int $customerId, ?int $pickedAddressId): ?int
    {
        if ($pickedAddressId !== null && Address::query()->ownedBy($customerId)->whereKey($pickedAddressId)->exists()) {
            return $pickedAddressId;
        }

        $address = Address::query()
            ->ownedBy($customerId)
            ->orderByDesc(Address::COLUMN_IS_DEFAULT)
            ->latest(Address::COLUMN_ID)
            ->first();

        return $address?->getId();
    }

    public function snapshot(int $customerId, int $addressId): ?AddressSnapshotData
    {
        $address = Address::query()->ownedBy($customerId)->find($addressId);
        $place = $address instanceof Address ? $this->locations->place($address->getCityId()) : null;

        if (! $address instanceof Address || $place === null) {
            return null;
        }

        return new AddressSnapshotData(
            addressId: $address->getId(),
            title: $address->getTitle(),
            receiverName: $address->getReceiverName(),
            receiverMobile: $address->getReceiverMobile(),
            provinceId: $place['province_id'],
            provinceName: $place['province'],
            cityId: $place['city_id'],
            cityName: $place['city'],
            mainStreet: $address->getMainStreet(),
            sideStreet: $address->getSideStreet(),
            alley: $address->getAlley(),
            block: $address->getBlock(),
            fullAddress: $address->getFullAddress(),
            plaque: $address->getPlaque(),
            unit: $address->getUnit(),
            floor: $address->getFloor(),
            postalCode: $address->getPostalCode(),
            isDeliveryEnabled: $place['is_delivery_enabled'],
        );
    }
}
