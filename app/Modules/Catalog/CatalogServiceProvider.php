<?php

declare(strict_types=1);

namespace App\Modules\Catalog;

use App\Modules\Catalog\Jobs\MarkNearExpiry;
use App\Modules\Shared\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

final class CatalogServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            // Near-expiry flags ("زودمصرف", roadmap D9): 02:00 Tehran time.
            $schedule->job(new MarkNearExpiry)->dailyAt('02:00')->timezone((string) config('app.display_timezone'))->onOneServer();
        });
    }
}
