<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Modules\Notifications\Drivers\KavenegarSmsSender;
use App\Modules\Notifications\Listeners\SendOrderSms;
use App\Modules\Notifications\Support\SmsManager;
use App\Modules\Orders\Events\OrderOnItsWay;
use App\Modules\Orders\Events\OrderPaid;
use App\Modules\Shared\ModuleServiceProvider;
use Illuminate\Support\Facades\Event;
use Kavenegar\KavenegarApi;

final class NotificationsServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        // Picks the SMS sender per provider (roadmap D11.6); one instance so senders are built once per request.
        $this->app->singleton(SmsManager::class);

        // Kavenegar's client from its package, with the key and address from config/sms.php (.env); tests swap it.
        $this->app->bind(KavenegarApi::class, fn (): KavenegarApi => KavenegarSmsSender::client(
            (string) config('sms.providers.kavenegar.api_key'),
            (string) config('sms.providers.kavenegar.base_url'),
        ));
    }

    public function boot(): void
    {
        parent::boot();

        // Order news by SMS (S37).
        Event::listen([OrderPaid::class, OrderOnItsWay::class], SendOrderSms::class);
    }
}
