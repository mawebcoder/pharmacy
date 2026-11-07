<?php

declare(strict_types=1);

namespace App\Modules\System\Jobs;

use App\Modules\System\Models\IntegrationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Writes one integration_logs row from the payload captured when the call happened. Saved through the model
 * so the request is encrypted at rest.
 */
final class WriteIntegrationLog implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 5;

    /**
     * @param  array<string, mixed>  $payload  IntegrationLog::COLUMN_* => value
     */
    public function __construct(public readonly array $payload) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function handle(): void
    {
        (new IntegrationLog)->forceFill($this->payload)->save();
    }
}
