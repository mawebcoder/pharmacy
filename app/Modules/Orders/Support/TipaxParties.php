<?php

declare(strict_types=1);

namespace App\Modules\Orders\Support;

use App\Modules\Identity\Support\AddressText;
use App\Modules\Locations\Queries\DeliveryLocations;
use App\Modules\Orders\Exceptions\OrderRefused;
use App\Modules\Orders\Models\Order;
use App\Modules\Settings\Queries\StoreSettings;
use App\Modules\Shipping\Data\TipaxPartyData;

/**
 * The two ends of an order's Tipax shipment (roadmap S35): the warehouse from the store settings, and the customer
 * from the order's copy of their address. A map point the order does not have is the city's.
 */
final readonly class TipaxParties
{
    public function __construct(
        private StoreSettings $settings,
        private DeliveryLocations $locations,
    ) {}

    /**
     * @throws OrderRefused when the sender details are incomplete
     */
    public function warehouse(): TipaxPartyData
    {
        $sender = $this->settings->shippingSender();
        $cityId = $this->settings->shippingOriginCityId();

        if ($cityId === null || $sender['name'] === '' || $sender['mobile'] === '' || $sender['address'] === '') {
            throw OrderRefused::senderIncomplete();
        }

        $place = $this->locations->place($cityId);

        return new TipaxPartyData(
            name: $sender['name'],
            mobile: $sender['mobile'],
            cityId: $cityId,
            address: $sender['address'],
            postalCode: $sender['postal_code'] !== '' ? $sender['postal_code'] : null,
            latitude: $sender['latitude'] !== '' ? $sender['latitude'] : self::coordinate($place['latitude'] ?? null),
            longitude: $sender['longitude'] !== '' ? $sender['longitude'] : self::coordinate($place['longitude'] ?? null),
        );
    }

    public function customer(Order $order): TipaxPartyData
    {
        $address = $order->getAddressSnapshot();
        $cityId = (int) ($address['city_id'] ?? 0);
        $place = $this->locations->place($cityId);

        return new TipaxPartyData(
            name: (string) ($address['receiver_name'] ?? $order->getCustomerSnapshot()['name'] ?? ''),
            mobile: (string) ($address['receiver_mobile'] ?? $order->getCustomerSnapshot()['mobile'] ?? ''),
            cityId: $cityId,
            address: AddressText::from($address)->line(),
            postalCode: isset($address['postal_code']) ? (string) $address['postal_code'] : null,
            plaque: isset($address['plaque']) ? (string) $address['plaque'] : null,
            unit: isset($address['unit']) ? (string) $address['unit'] : null,
            floor: isset($address['floor']) ? (string) $address['floor'] : null,
            latitude: self::coordinate($place['latitude'] ?? null),
            longitude: self::coordinate($place['longitude'] ?? null),
        );
    }

    private static function coordinate(?float $value): ?string
    {
        return $value === null || $value === 0.0 ? null : (string) $value;
    }
}
