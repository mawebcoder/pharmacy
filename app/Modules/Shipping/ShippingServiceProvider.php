<?php

declare(strict_types=1);

namespace App\Modules\Shipping;

use App\Modules\Orders\Events\OrderPaid;
use App\Modules\Settings\Queries\StoreSettings;
use App\Modules\Shared\ModuleServiceProvider;
use App\Modules\Shipping\Enums\TipaxDistributionType;
use App\Modules\Shipping\Enums\TipaxPaymentType;
use App\Modules\Shipping\Enums\TipaxPickupType;
use App\Modules\Shipping\Enums\TipaxPriceUnit;
use App\Modules\Shipping\Listeners\PackPaidOrder;
use App\Modules\Shipping\Pricing\ShippingPriceCalculator;
use App\Modules\Shipping\Pricing\Strategies\FlatRateShippingPrice;
use App\Modules\Shipping\Pricing\Strategies\TipaxShippingPrice;
use App\Modules\Shipping\Tipax\Adapters\TipaxPriceRequestAdapter;
use App\Modules\Shipping\Tipax\Adapters\TipaxPriceResponseAdapter;
use App\Modules\Shipping\Tipax\TipaxClient;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;

final class ShippingServiceProvider extends ModuleServiceProvider
{
    /**
     * The container tag of the shipping price strategies (roadmap S34).
     */
    public const string PRICE_STRATEGIES = 'shipping.price_strategies';

    public function register(): void
    {
        // The Tipax client from config/shipping.php (roadmap D11.3).
        $this->app->singleton(TipaxClient::class, fn (): TipaxClient => new TipaxClient(
            baseUrl: (string) config('shipping.tipax.base_url'),
            username: config('shipping.tipax.username'),
            password: config('shipping.tipax.password'),
            apiKey: config('shipping.tipax.api_key'),
            paths: config('shipping.tipax.paths'),
            connectTimeoutSeconds: (int) config('shipping.tipax.connect_timeout_seconds'),
            timeoutSeconds: (int) config('shipping.tipax.timeout_seconds'),
            retries: (int) config('shipping.tipax.retries'),
            retrySleepMs: (int) config('shipping.tipax.retry_sleep_ms'),
            pricingTimeoutSeconds: (int) config('shipping.tipax.pricing_timeout_seconds'),
        ));

        // Tipax's pricing adapters (roadmap S34): the codes sent with every package, and the unit of its prices.
        $this->app->singleton(TipaxPriceRequestAdapter::class, fn (): TipaxPriceRequestAdapter => new TipaxPriceRequestAdapter(
            paymentType: TipaxPaymentType::from((int) config('shipping.tipax.pricing.payment_type')),
            pickupType: TipaxPickupType::from((int) config('shipping.tipax.pricing.pickup_type')),
            distributionType: TipaxDistributionType::from((int) config('shipping.tipax.pricing.distribution_type')),
            packingId: (int) config('shipping.tipax.pricing.packing_id'),
            packType: (int) config('shipping.tipax.pricing.pack_type'),
            contentGeneralId: (int) config('shipping.tipax.pricing.content_general_id'),
            contentLiquidId: (int) config('shipping.tipax.pricing.content_liquid_id'),
            unboxedSizeCm: (int) config('shipping.tipax.pricing.unboxed_size_cm'),
        ));
        $this->app->singleton(TipaxPriceResponseAdapter::class, fn (): TipaxPriceResponseAdapter => new TipaxPriceResponseAdapter(
            priceUnit: TipaxPriceUnit::from((string) config('shipping.tipax.pricing.price_unit')),
        ));
        $this->app->bind(TipaxShippingPrice::class, fn (Application $app): TipaxShippingPrice => new TipaxShippingPrice(
            client: $app->make(TipaxClient::class),
            requestAdapter: $app->make(TipaxPriceRequestAdapter::class),
            responseAdapter: $app->make(TipaxPriceResponseAdapter::class),
            enabled: (bool) config('shipping.tipax.enabled'),
            deliveryDaysCacheHours: (int) config('shipping.pricing.delivery_days_cache_hours'),
        ));

        // One strategy per shipping service; a new service (e.g. the own courier, S32) is added to this list.
        $this->app->tag([TipaxShippingPrice::class], self::PRICE_STRATEGIES);
        $this->app->bind(ShippingPriceCalculator::class, fn (Application $app): ShippingPriceCalculator => new ShippingPriceCalculator(
            strategies: $app->tagged(self::PRICE_STRATEGIES),
            fallback: $app->make(FlatRateShippingPrice::class),
            settings: $app->make(StoreSettings::class),
            cacheMinutes: (int) config('shipping.pricing.cache_minutes'),
            roundUpToRial: (int) config('shipping.pricing.round_up_to_rial'),
        ));
    }

    public function boot(): void
    {
        parent::boot();

        // A paid order is packed into parcels (roadmap S31, owner 2026-11-17).
        Event::listen(OrderPaid::class, PackPaidOrder::class);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            // Provinces and cities from Tipax (roadmap D4.3): Saturdays 03:00 Tehran time, besides every deploy.
            $schedule->command('tipax:sync-cities')->weeklyOn(6, '03:00')->timezone((string) config('app.display_timezone'))->onOneServer();
        });
    }
}
