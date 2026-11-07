<?php

declare(strict_types=1);

namespace App\Modules\System\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * The HTTP client for external services: every call is logged, redacted, in integration_logs (roadmap D0.4).
 * IntegrationHttp::for('tipax', 'pricing.quote', orderId: 12)->post(…), or Http::integration(…) for short.
 * Fields secret only for one call are passed in $redact, e.g. redact: ['text'] for the OTP sent by SMS.
 */
final class IntegrationHttp
{
    /**
     * @param  list<string>  $redact
     */
    public static function for(string $service, string $operation, ?int $orderId = null, array $redact = []): PendingRequest
    {
        return Http::withMiddleware(new LogIntegrationCalls($service, $operation, $orderId, $redact));
    }
}
