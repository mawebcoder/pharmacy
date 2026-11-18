<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Tipax\Adapters;

use App\Modules\Shipping\Data\ShippingOptionData;
use App\Modules\Shipping\Enums\TipaxPriceUnit;
use App\Modules\Shipping\Exceptions\ShippingPriceUnavailable;

/**
 * Turns Tipax's answers into our data (roadmap D6.4, S34; adapter, owner 2026-11-18):
 * - `GetServicesBetweenCities` → the services on a route, with delivery time in days (Tipax gives minutes: 4320 for
 *   Tehran–Isfahan);
 * - `POST Pricing` for one service → one option. The answer has one entry per package; each fills one of its rate
 *   fields (e.g. `regularRate` even for «ارسال زمینی همان روز»), and the option's price is the sum of their
 *   `finalPrice` (with tax and insurance) in rial. A package without a price means the service does not take the order.
 */
final readonly class TipaxPriceResponseAdapter
{
    /**
     * The rate fields of ETipaxPriceMinListDto.
     */
    public const array RATE_FIELDS = ['regularRate', 'regularPlusRate', 'expressRate', 'sameDayExpressRate', 'airExpressRate'];

    private const int MINUTES_PER_DAY = 1_440;

    public function __construct(
        private TipaxPriceUnit $priceUnit,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $serviceList  GetServicesBetweenCities' serviceList
     * @return list<array{id: int, kind: string, days: int|null}>
     */
    public function toServices(array $serviceList): array
    {
        $services = [];

        foreach ($serviceList as $service) {
            if (! is_numeric($service['serviceId'] ?? null) || (int) $service['serviceId'] <= 0) {
                continue;
            }

            $minutes = is_numeric($service['deliveryDay'] ?? null) ? (int) $service['deliveryDay'] : 0;
            $services[] = [
                'id' => (int) $service['serviceId'],
                'kind' => trim(is_string($service['serviceKind'] ?? null) ? $service['serviceKind'] : ''),
                'days' => $minutes > 0 ? (int) ceil($minutes / self::MINUTES_PER_DAY) : null,
            ];
        }

        return $services;
    }

    /**
     * @param  list<array<string, mixed>>  $entries  one per package, in the order sent
     * @param  array{id: int, kind: string, days: int|null}  $service
     *
     * @throws ShippingPriceUnavailable when the answer has not one entry per package
     */
    public function toOption(array $entries, int $packageCount, array $service, bool $isEstimate): ?ShippingOptionData
    {
        if (count($entries) !== $packageCount) {
            throw ShippingPriceUnavailable::unreadable('Tipax');
        }

        $total = 0;
        $serviceTitle = null;

        foreach ($entries as $entry) {
            $rate = $this->rate($entry);

            if ($rate === null) {
                return null;
            }

            $total += $this->priceUnit->toRial((float) $rate['finalPrice']);
            $serviceTitle ??= is_string($rate['serviceTitle'] ?? null) ? trim($rate['serviceTitle']) : null;
        }

        return new ShippingOptionData(
            key: 'tipax.'.$service['id'],
            method: 'tipax',
            title: 'تیپاکس '.($service['kind'] !== '' ? $service['kind'] : ($serviceTitle ?? '')),
            price: $total,
            deliveryDays: $service['days'],
            isEstimate: $isEstimate,
            details: [
                'service_id' => $service['id'],
                'service_kind' => $service['kind'],
                'service_title' => $serviceTitle,
                'packages' => $packageCount,
            ],
        );
    }

    /**
     * The package's priced rate, whichever field Tipax put it in.
     *
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>|null
     */
    private function rate(array $entry): ?array
    {
        foreach (self::RATE_FIELDS as $field) {
            $rate = $entry[$field] ?? null;

            if (is_array($rate) && is_numeric($rate['finalPrice'] ?? null) && (float) $rate['finalPrice'] > 0) {
                return $rate;
            }
        }

        return null;
    }
}
