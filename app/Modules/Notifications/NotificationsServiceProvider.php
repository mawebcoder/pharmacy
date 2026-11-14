<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Modules\Notifications\Support\SmsManager;
use App\Modules\Shared\ModuleServiceProvider;

final class NotificationsServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        // Picks the SMS sender per provider (roadmap D11.6); one instance so senders are built once per request.
        $this->app->singleton(SmsManager::class);
    }
}
