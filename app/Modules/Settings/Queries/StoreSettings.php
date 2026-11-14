<?php

declare(strict_types=1);

namespace App\Modules\Settings\Queries;

use App\Modules\Settings\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Typed store settings with their defaults (roadmap D3.6, started in S19), cached until SaveStoreSettings changes
 * them. Other modules read settings only through this query.
 */
final readonly class StoreSettings
{
    public const string CACHE_KEY = 'settings:store';

    public const string MAX_QUANTITY_PER_ORDER = 'cart.max_quantity_per_order';

    public const string FREE_SHIPPING_THRESHOLD = 'cart.free_shipping_threshold';

    /**
     * Defaults when the admin has not saved a value (the threshold in rial).
     */
    public const array DEFAULTS = [
        self::MAX_QUANTITY_PER_ORDER => 10,
        self::FREE_SHIPPING_THRESHOLD => 5_000_000,
    ];

    /**
     * The store-wide limit per SKU in one order, used when neither the SKU nor its product sets one.
     */
    public function maxQuantityPerOrder(): int
    {
        return max(1, (int) $this->get(self::MAX_QUANTITY_PER_ORDER));
    }

    /**
     * Orders from this amount (rial) ship free; 0 turns the offer off.
     */
    public function freeShippingThreshold(): int
    {
        return max(0, (int) $this->get(self::FREE_SHIPPING_THRESHOLD));
    }

    public function get(string $key): mixed
    {
        return $this->all()[$key] ?? self::DEFAULTS[$key] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        /** @var array<string, mixed> $saved */
        $saved = Cache::rememberForever(self::CACHE_KEY, fn (): array => Setting::query()->pluck(Setting::COLUMN_VALUE, Setting::COLUMN_KEY)->all());

        return [...self::DEFAULTS, ...$saved];
    }
}
