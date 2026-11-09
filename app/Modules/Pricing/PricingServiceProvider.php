<?php

declare(strict_types=1);

namespace App\Modules\Pricing;

use App\Modules\Catalog\Events\PricingInputsChanged;
use App\Modules\Pricing\Listeners\QueuePriceRecalculation;
use App\Modules\Shared\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;

final class PricingServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        Event::listen(PricingInputsChanged::class, QueuePriceRecalculation::class);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            // Safety net for prices (roadmap D9): re-price every SKU at 03:00 Tehran time; only real changes are recorded.
            $schedule->command('pricing:recalculate')->dailyAt('03:00')->timezone((string) config('app.display_timezone'))->onOneServer();
        });
    }
}
