<?php

declare(strict_types=1);

namespace App\Modules\Search;

use App\Modules\Search\Jobs\RecalculatePopularProducts;
use App\Modules\Shared\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

final class SearchServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            // «محصولات محبوب» from the last 30 days of search clicks: nightly 03:30 Tehran time (owner, 2026-11-14).
            $schedule->job(new RecalculatePopularProducts)->dailyAt('03:30')->timezone((string) config('app.display_timezone'))->onOneServer();
        });
    }
}
