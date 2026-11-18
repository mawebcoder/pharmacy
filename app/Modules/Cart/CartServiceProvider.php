<?php

declare(strict_types=1);

namespace App\Modules\Cart;

use App\Modules\Cart\Contracts\CustomerOrderHistory;
use App\Modules\Cart\Jobs\PruneGuestCarts;
use App\Modules\Cart\Jobs\ReleaseExpiredCouponReservations;
use App\Modules\Cart\Listeners\MergeGuestCartOnLogin;
use App\Modules\Cart\Support\NoOrdersYet;
use App\Modules\Identity\Events\CustomerLoggedIn;
use App\Modules\Shared\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;

final class CartServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        // «First order only» coupons ask the order history; the Orders module (S23) replaces this binding.
        $this->app->bindIf(CustomerOrderHistory::class, NoOrdersYet::class);
    }

    public function boot(): void
    {
        parent::boot();

        // A guest's cart joins the customer's on sign-in (roadmap D13.3).
        Event::listen(CustomerLoggedIn::class, MergeGuestCartOnLogin::class);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            // Expired guest carts: daily 04:00 Tehran time.
            $schedule->job(new PruneGuestCarts)->dailyAt('04:00')->timezone((string) config('app.display_timezone'))->onOneServer();

            // Coupon reservations whose payment never completed (S22).
            $schedule->job(new ReleaseExpiredCouponReservations)->everyFiveMinutes()->onOneServer();
        });
    }
}
