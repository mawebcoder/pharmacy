<?php

declare(strict_types=1);

namespace App\Modules\Orders;

use App\Modules\Cart\Contracts\CustomerOrderHistory;
use App\Modules\Orders\Jobs\ExpirePendingOrders;
use App\Modules\Orders\Listeners\MarkOrderPaid;
use App\Modules\Orders\Queries\PlacedOrderHistory;
use App\Modules\Payments\Events\PaymentSucceeded;
use App\Modules\Shared\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;

final class OrdersServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        // «First order only» coupons ask the real order history (S25 replaces Cart's NoOrdersYet).
        $this->app->bind(CustomerOrderHistory::class, PlacedOrderHistory::class);
    }

    public function boot(): void
    {
        parent::boot();

        Event::listen(PaymentSucceeded::class, MarkOrderPaid::class);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            // Unpaid orders past their payment window (S25).
            $schedule->job(new ExpirePendingOrders)->everyMinute()->onOneServer();
        });
    }
}
