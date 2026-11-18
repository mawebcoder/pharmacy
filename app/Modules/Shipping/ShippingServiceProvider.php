<?php

declare(strict_types=1);

namespace App\Modules\Shipping;

use App\Modules\Orders\Events\OrderPaid;
use App\Modules\Shared\ModuleServiceProvider;
use App\Modules\Shipping\Listeners\PackPaidOrder;
use App\Modules\Shipping\Tipax\TipaxClient;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;

final class ShippingServiceProvider extends ModuleServiceProvider
{
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
