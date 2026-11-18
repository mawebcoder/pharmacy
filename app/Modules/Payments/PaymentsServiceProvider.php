<?php

declare(strict_types=1);

namespace App\Modules\Payments;

use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Gateways\FakePaymentGateway;
use App\Modules\Payments\Gateways\Zarinpal\LaravelPsrClient;
use App\Modules\Payments\Gateways\Zarinpal\ZarinpalClient;
use App\Modules\Payments\Gateways\Zarinpal\ZarinpalGateway;
use App\Modules\Payments\Jobs\ExpireStalePayments;
use App\Modules\Payments\Jobs\ReconcileZarinpalPayments;
use App\Modules\Shared\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;

final class PaymentsServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        // Zarinpal through its SDK (S36): sandbox or production and every address from config/payments.php (.env).
        $this->app->singleton(ZarinpalClient::class, fn (): ZarinpalClient => new ZarinpalClient(
            settings: [
                'merchant_id' => config('payments.zarinpal.merchant_id'),
                'sandbox' => (bool) config('payments.zarinpal.sandbox'),
                'base_url' => (string) config('payments.zarinpal.base_url'),
                'sandbox_base_url' => (string) config('payments.zarinpal.sandbox_base_url'),
                'currency' => (string) config('payments.zarinpal.currency'),
            ],
            http: new LaravelPsrClient((int) config('payments.zarinpal.timeout_seconds')),
            timeoutSeconds: (int) config('payments.zarinpal.timeout_seconds'),
        ));

        // The gateway from config/payments.php: «zarinpal», or «fake» for local and staging.
        $this->app->bind(PaymentGateway::class, fn (Application $app): PaymentGateway => match (config('payments.gateway')) {
            ZarinpalGateway::NAME => new ZarinpalGateway($app->make(ZarinpalClient::class), config('payments.zarinpal.callback_url')),
            FakePaymentGateway::NAME => new FakePaymentGateway,
            default => throw new InvalidArgumentException('Unknown payment gateway «'.config('payments.gateway').'».'),
        });
    }

    public function boot(): void
    {
        parent::boot();

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            // Payment attempts whose window passed without a callback (S23).
            $schedule->job(new ExpireStalePayments)->everyMinute()->onOneServer();
            // Zarinpal payments taken but never verified (S36); does nothing with another gateway.
            $schedule->job(new ReconcileZarinpalPayments)->cron('*/'.(int) config('payments.zarinpal.reconcile_every_minutes').' * * * *')->onOneServer();
        });
    }
}
