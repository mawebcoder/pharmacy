<?php

declare(strict_types=1);

namespace App\Modules\Cart;

use App\Modules\Cart\Jobs\PruneGuestCarts;
use App\Modules\Cart\Listeners\MergeGuestCartOnLogin;
use App\Modules\Identity\Events\CustomerLoggedIn;
use App\Modules\Shared\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;

final class CartServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        // A guest's cart joins the customer's on sign-in (roadmap D13.3).
        Event::listen(CustomerLoggedIn::class, MergeGuestCartOnLogin::class);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            // Expired guest carts: daily 04:00 Tehran time.
            $schedule->job(new PruneGuestCarts)->dailyAt('04:00')->timezone((string) config('app.display_timezone'))->onOneServer();
        });
    }
}
