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
use App\Modules\Shipping\Pricing\Strategies\RateTableShippingPrice;
use App\Modules\Shipping\Pricing\Strategies\TipaxShippingPrice;
use App\Modules\Shipping\Queries\ParcelTrackingStatus;
use App\Modules\Shipping\Tipax\Adapters\TipaxOrderRequestAdapter;
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

        // Tipax's pricing adapters (roadmap S34): the codes sent with every package (picked in the store settings, S33,
        // else .env), and the unit of its prices. Built per use, so a changed setting applies at once, in workers too.
        $this->app->bind(TipaxPriceRequestAdapter::class, function (Application $app): TipaxPriceRequestAdapter {
            $settings = $app->make(StoreSettings::class);

            return new TipaxPriceRequestAdapter(
                paymentType: TipaxPaymentType::from((int) config('shipping.tipax.pricing.payment_type')),
                pickupType: TipaxPickupType::from((int) config('shipping.tipax.pricing.pickup_type')),
                distributionType: TipaxDistributionType::from((int) config('shipping.tipax.pricing.distribution_type')),
                packingId: $settings->tipaxCode(StoreSettings::TIPAX_PACKING_ID) ?? (int) config('shipping.tipax.pricing.packing_id'),
                packType: $settings->tipaxCode(StoreSettings::TIPAX_PACK_TYPE) ?? (int) config('shipping.tipax.pricing.pack_type'),
                contentGeneralId: $settings->tipaxCode(StoreSettings::TIPAX_CONTENT_GENERAL_ID) ?? (int) config('shipping.tipax.pricing.content_general_id'),
                contentLiquidId: $settings->tipaxCode(StoreSettings::TIPAX_CONTENT_LIQUID_ID) ?? (int) config('shipping.tipax.pricing.content_liquid_id'),
                unboxedSizeCm: (int) config('shipping.tipax.pricing.unboxed_size_cm'),
                minWeightKg: (float) config('shipping.tipax.pricing.min_weight_kg'),
            );
        });
        $this->app->singleton(TipaxPriceResponseAdapter::class, fn (): TipaxPriceResponseAdapter => new TipaxPriceResponseAdapter(
            priceUnit: TipaxPriceUnit::from((string) config('shipping.tipax.pricing.price_unit')),
        ));
        $this->app->bind(TipaxShippingPrice::class, fn (Application $app): TipaxShippingPrice => new TipaxShippingPrice(
            client: $app->make(TipaxClient::class),
            requestAdapter: $app->make(TipaxPriceRequestAdapter::class),
            responseAdapter: $app->make(TipaxPriceResponseAdapter::class),
            enabled: (bool) config('shipping.tipax.enabled'),
            servicesCacheHours: (int) config('shipping.pricing.services_cache_hours'),
        ));

        // Registering orders with Tipax (S35): the same codes as pricing (store settings, else .env).
        $this->app->bind(TipaxOrderRequestAdapter::class, function (Application $app): TipaxOrderRequestAdapter {
            $settings = $app->make(StoreSettings::class);

            return new TipaxOrderRequestAdapter(
                paymentType: TipaxPaymentType::from((int) config('shipping.tipax.pricing.payment_type')),
                pickupType: TipaxPickupType::from((int) config('shipping.tipax.pricing.pickup_type')),
                distributionType: TipaxDistributionType::from((int) config('shipping.tipax.pricing.distribution_type')),
                packingId: $settings->tipaxCode(StoreSettings::TIPAX_PACKING_ID) ?? (int) config('shipping.tipax.pricing.packing_id'),
                packType: $settings->tipaxCode(StoreSettings::TIPAX_PACK_TYPE) ?? (int) config('shipping.tipax.pricing.pack_type'),
                contentGeneralId: $settings->tipaxCode(StoreSettings::TIPAX_CONTENT_GENERAL_ID) ?? (int) config('shipping.tipax.pricing.content_general_id'),
                contentLiquidId: $settings->tipaxCode(StoreSettings::TIPAX_CONTENT_LIQUID_ID) ?? (int) config('shipping.tipax.pricing.content_liquid_id'),
                unboxedSizeCm: (int) config('shipping.tipax.pricing.unboxed_size_cm'),
                minWeightKg: (float) config('shipping.tipax.pricing.min_weight_kg'),
            );
        });

        // A parcel's status on the order page, asked from Tipax at most once per «tracking_refresh_minutes» (S37).
        $this->app->bind(ParcelTrackingStatus::class, fn (Application $app): ParcelTrackingStatus => new ParcelTrackingStatus(
            $app->make(TipaxClient::class),
            (int) config('shipping.pricing.tracking_refresh_minutes'),
        ));

        // One strategy per shipping service; a new service (e.g. the own courier, S32) is added to this list.
        $this->app->tag([TipaxShippingPrice::class], self::PRICE_STRATEGIES);
        $this->app->bind(ShippingPriceCalculator::class, fn (Application $app): ShippingPriceCalculator => new ShippingPriceCalculator(
            strategies: $app->tagged(self::PRICE_STRATEGIES),
            fallbacks: [$app->make(RateTableShippingPrice::class), $app->make(FlatRateShippingPrice::class)],
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
            // Provinces and cities from Tipax (roadmap D4.3): every night at 00:00 Tehran time (owner, 2026-11-18), besides every deploy.
            $schedule->command('tipax:sync-cities')->dailyAt('00:00')->timezone((string) config('app.display_timezone'))->onOneServer();
            // Tipax's services, pack types, packings and contents (S33), right after the cities.
            $schedule->command('tipax:sync-reference')->dailyAt('00:10')->timezone((string) config('app.display_timezone'))->onOneServer();
        });
    }
}
