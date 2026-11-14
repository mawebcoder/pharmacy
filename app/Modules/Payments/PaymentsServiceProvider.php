<?php

declare(strict_types=1);

namespace App\Modules\Payments;

use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Gateways\FakePaymentGateway;
use App\Modules\Payments\Jobs\ExpireStalePayments;
use App\Modules\Shared\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use InvalidArgumentException;

final class PaymentsServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        // The gateway from config/payments.php; S26 adds «zarinpal».
        $this->app->bind(PaymentGateway::class, fn (): PaymentGateway => match (config('payments.gateway')) {
            FakePaymentGateway::NAME => new FakePaymentGateway,
            default => throw new InvalidArgumentException('Unknown payment gateway «'.config('payments.gateway').'».'),
        });
    }

    public function boot(): void
    {
        parent::boot();

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            // Payment attempts whose window passed without a callback (S25).
            $schedule->job(new ExpireStalePayments)->everyMinute()->onOneServer();
        });
    }
}
