<?php

declare(strict_types=1);

namespace App\Modules\System;

use App\Modules\Shared\ModuleServiceProvider;
use App\Modules\System\Models\IntegrationLog;
use App\Modules\System\Policies\ActivityPolicy;
use App\Modules\System\Support\IntegrationHttp;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Models\Activity;

final class SystemServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        Gate::policy(Activity::class, ActivityPolicy::class);

        // Http::integration('tipax', 'pricing.quote') → a client whose calls are logged (see IntegrationHttp).
        Http::macro('integration', fn (string $service, string $operation, ?int $orderId = null, array $redact = []): PendingRequest => IntegrationHttp::for($service, $operation, $orderId, array_values(array_map(strval(...), $redact))));

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            // Integration logs keep raw API responses: keep config('integrations.log.retention_days') days.
            $schedule->command('model:prune', ['--model' => [IntegrationLog::class]])->daily()->onOneServer();
        });
    }
}
