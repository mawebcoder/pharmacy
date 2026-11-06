<?php

declare(strict_types=1);

namespace App\Modules\System\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Spatie\Activitylog\Support\Config;

/**
 * Writes one audit-log row from a payload captured when the change happened (roadmap D16).
 * The payload already holds the database values (changes JSON, causer, subject, time), so nothing is re-read.
 */
final class WriteActivityLog implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 5;

    /**
     * @param  array<string, mixed>  $payload  Column => value for the activity_log table.
     */
    public function __construct(public readonly array $payload)
    {
        $this->afterCommit();
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function handle(): void
    {
        /** @var class-string<Model> $activityModel */
        $activityModel = Config::activityModel();

        $activityModel::query()->insert($this->payload);
    }
}
