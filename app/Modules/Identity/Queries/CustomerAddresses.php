<?php

declare(strict_types=1);

namespace App\Modules\Identity\Queries;

use App\Modules\Identity\Data\AddressSnapshotData;
use App\Modules\Identity\Models\Address;
use App\Modules\Locations\Queries\DeliveryLocations;

/**
 * A customer's addresses, read by other modules: for checkout (roadmap S23), which address an order goes to (the one
 * picked on the address step if it is still theirs, else their default, else their newest) and its snapshot; for the
 * account dashboard (Profile), how many there are and the default one.
 */
final readonly class CustomerAddresses
{
    public function __construct(private DeliveryLocations $locations) {}

    public function hasAny(int $customerId): bool
    {
        return Address::query()->ownedBy($customerId)->exists();
    }

    /**
     * The customer's saved addresses for a dropdown (owner, 2026-11-19: «ثبت سفارش» in the panel): title and receiver.
     *
     * @return array<int, string> address id => label
     */
    public function options(int $customerId): array
    {
        $options = [];

        foreach (Address::query()->ownedBy($customerId)->orderByDesc(Address::COLUMN_IS_DEFAULT)->orderBy(Address::COLUMN_ID)->get() as $address) {
            $options[$address->getId()] = trim($address->getTitle().' · '.$address->getReceiverName().' · '.$address->getMainStreet(), ' ·');
        }

        return $options;
    }

    public function count(int $customerId): int
    {
        return Address::query()->ownedBy($customerId)->count();
    }

    /**
     * The address the customer marked as default, for the account dashboard; null when none is marked.
     */
    public function defaultSnapshot(int $customerId): ?AddressSnapshotData
    {
        $addressId = Address::query()->ownedBy($customerId)->where(Address::COLUMN_IS_DEFAULT, true)->value(Address::COLUMN_ID);

        return $addressId === null ? null : $this->snapshot($customerId, (int) $addressId);
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
