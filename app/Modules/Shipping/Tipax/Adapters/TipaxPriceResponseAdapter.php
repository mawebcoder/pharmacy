<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Tipax\Adapters;

use App\Modules\Shipping\Data\ShippingOptionData;
use App\Modules\Shipping\Enums\TipaxPriceUnit;
use App\Modules\Shipping\Exceptions\ShippingPriceUnavailable;

/**
 * Turns Tipax's `POST Pricing` answer into our options (roadmap D6.4, S34; adapter, owner 2026-11-18). The answer has
 * one entry per package, each with a rate per service family; a family becomes one option when every package has a
 * price for it, priced as the sum of their `finalPrice` (with tax and discount) in rial. Delivery days come from
 * GetServicesBetweenCities by service id.
 */
final readonly class TipaxPriceResponseAdapter
{
    /**
     * Tipax's rate fields => our option key and title, in the order offered.
     */
    public const array FAMILIES = [
        'regularRate' => ['key' => 'regular', 'title' => 'تیپاکس عادی'],
        'regularPlusRate' => ['key' => 'regular_plus', 'title' => 'تیپاکس عادی پلاس'],
        'expressRate' => ['key' => 'express', 'title' => 'تیپاکس اکسپرس'],
        'sameDayExpressRate' => ['key' => 'same_day', 'title' => 'تیپاکس همان روز'],
        'airExpressRate' => ['key' => 'air_express', 'title' => 'تیپاکس اکسپرس هوایی'],
    ];

    public function __construct(
        private TipaxPriceUnit $priceUnit,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $entries  one per package, in the order sent
     * @param  array<int, int>  $deliveryDays  service id => days
     * @return list<ShippingOptionData>
     *
     * @throws ShippingPriceUnavailable
     */
    public function toOptions(array $entries, int $packageCount, array $deliveryDays, bool $isEstimate): array
    {
        if ($entries === [] || count($entries) !== $packageCount) {
            throw ShippingPriceUnavailable::unreadable('Tipax');
        }

        $options = [];

        foreach (self::FAMILIES as $field => $family) {
            $total = 0;
            $serviceId = null;
            $serviceTitle = null;

            foreach ($entries as $entry) {
                $rate = $entry[$field] ?? null;
                $price = is_array($rate) && is_numeric($rate['finalPrice'] ?? null) ? (float) $rate['finalPrice'] : 0.0;

                if ($price <= 0) {
                    // Not offered for this package, so not for the order.
                    continue 2;
                }

                $total += $this->priceUnit->toRial($price);
                $serviceId ??= is_numeric($rate['serviceId'] ?? null) ? (int) $rate['serviceId'] : null;
                $serviceTitle ??= is_string($rate['serviceTitle'] ?? null) ? $rate['serviceTitle'] : null;
            }

            $options[] = new ShippingOptionData(
                key: 'tipax.'.$family['key'],
                method: 'tipax',
                title: $family['title'],
                price: $total,
                deliveryDays: $serviceId === null ? null : ($deliveryDays[$serviceId] ?? null),
                isEstimate: $isEstimate,
                details: [
                    'family' => $field,
                    'service_id' => $serviceId,
                    'service_title' => $serviceTitle,
                    'packages' => count($entries),
                ],
            );
        }

        return $options;
    }
}
