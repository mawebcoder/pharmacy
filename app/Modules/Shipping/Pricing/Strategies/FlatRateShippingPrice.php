<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Pricing\Strategies;

use App\Modules\Settings\Queries\StoreSettings;
use App\Modules\Shipping\Contracts\ShippingPriceStrategy;
use App\Modules\Shipping\Data\ShippingOptionData;
use App\Modules\Shipping\Data\ShippingPriceRequest;

/**
 * The store's flat shipping rate («هزینه ارسال» in the store settings) as a strategy (roadmap S34): the fallback when
 * no service can price the order, so checkout never stops. Its price is marked an estimate («تخمینی»).
 */
final readonly class FlatRateShippingPrice implements ShippingPriceStrategy
{
    public const string KEY = 'flat';

    public function __construct(
        private StoreSettings $settings,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function supports(ShippingPriceRequest $request): bool
    {
        return true;
    }

    public function options(ShippingPriceRequest $request): array
    {
        return [self::option($this->settings->shippingFlatRate())];
    }

    /**
     * The flat-rate option, also for an order with no address yet.
     */
    public static function option(int $flatRate): ShippingOptionData
    {
        return new ShippingOptionData(
            key: self::KEY,
            method: self::KEY,
            title: 'ارسال',
            price: $flatRate,
            deliveryDays: null,
            isEstimate: true,
        );
    }
}
