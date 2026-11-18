<?php

declare(strict_types=1);

namespace App\Modules\Settings\Queries;

use App\Modules\Orders\Queries\DeliveryStats;
use Illuminate\Support\Facades\Cache;

/**
 * The trust counters under the trust block (roadmap B7 Daroodrug, S30): delivered orders and cities delivered to,
 * counted live and cached for an hour. No product count anywhere on the site (owner, 2026-11-17). A counter is left
 * out while it is too small to reassure anyone (fewer than 100 orders or 10 cities; owner, 2026-11-17).
 */
final readonly class TrustCounters
{
    public const string CACHE_KEY = 'settings:trust-counters';

    public const int MIN_ORDERS = 100;

    public const int MIN_CITIES = 10;

    public function __construct(
        private DeliveryStats $deliveries,
    ) {}

    /**
     * @return list<array{icon: string, value: int, label: string}>
     */
    public function shown(): array
    {
        /** @var array{orders: int, cities: int} $counts */
        $counts = Cache::remember(self::CACHE_KEY, now()->addHour(), fn (): array => [
            'orders' => $this->deliveries->deliveredOrders(),
            'cities' => $this->deliveries->citiesDeliveredTo(),
        ]);

        return array_values(array_filter([
            $counts['orders'] >= self::MIN_ORDERS ? ['icon' => 'check-badge', 'value' => $counts['orders'], 'label' => 'سفارش تحویل‌شده'] : null,
            $counts['cities'] >= self::MIN_CITIES ? ['icon' => 'map-pin', 'value' => $counts['cities'], 'label' => 'شهر زیر پوشش'] : null,
        ]));
    }
}
