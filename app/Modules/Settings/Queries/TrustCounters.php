<?php

declare(strict_types=1);

namespace App\Modules\Settings\Queries;

use App\Modules\Catalog\Queries\ProductCounts;
use App\Modules\Orders\Queries\DeliveryStats;
use Illuminate\Support\Facades\Cache;

/**
 * The trust counters under the trust block (roadmap B7 Daroodrug, S35): products for sale, delivered orders and
 * cities delivered to, counted live and cached for an hour. A counter is left out while it is too small to reassure
 * anyone (fewer than 100 orders or 10 cities; owner, 2026-11-17).
 */
final readonly class TrustCounters
{
    public const string CACHE_KEY = 'settings:trust-counters';

    public const int MIN_PRODUCTS = 1;

    public const int MIN_ORDERS = 100;

    public const int MIN_CITIES = 10;

    public function __construct(
        private ProductCounts $products,
        private DeliveryStats $deliveries,
    ) {}

    /**
     * The counters big enough to show; the footer leaves out the products for sale (owner, 2026-11-17).
     *
     * @return list<array{icon: string, value: int, label: string}>
     */
    public function shown(bool $withProducts = true): array
    {
        /** @var array{products: int, orders: int, cities: int} $counts */
        $counts = Cache::remember(self::CACHE_KEY, now()->addHour(), fn (): array => [
            'products' => $this->products->sellable(),
            'orders' => $this->deliveries->deliveredOrders(),
            'cities' => $this->deliveries->citiesDeliveredTo(),
        ]);

        return array_values(array_filter([
            $withProducts && $counts['products'] >= self::MIN_PRODUCTS ? ['icon' => 'cube', 'value' => $counts['products'], 'label' => 'کالای موجود'] : null,
            $counts['orders'] >= self::MIN_ORDERS ? ['icon' => 'check-badge', 'value' => $counts['orders'], 'label' => 'سفارش تحویل‌شده'] : null,
            $counts['cities'] >= self::MIN_CITIES ? ['icon' => 'map-pin', 'value' => $counts['cities'], 'label' => 'شهر زیر پوشش'] : null,
        ]));
    }
}
