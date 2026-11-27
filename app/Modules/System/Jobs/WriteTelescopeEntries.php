<?php

declare(strict_types=1);

namespace App\Modules\System\Jobs;

use App\Modules\System\Support\QueuedTelescopeEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Storage\DatabaseEntriesRepository;
use Laravel\Telescope\Telescope;

/**
 * Writes one batch of Telescope entries queued by QueuedTelescopeRepository. It runs without recording, so its own
 * queries are not recorded and queued again.
 */
final class WriteTelescopeEntries implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    /**
     * @param  list<array{uuid: string, type: string, family_hash: string|null, content: array<mixed>, tags: array<mixed>, created_at: string, exception: bool}>  $entries
     */
    public function __construct(public readonly string $batchId, public readonly array $entries) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(): void
    {
        $repository = new DatabaseEntriesRepository(
            (string) config('telescope.storage.database.connection'),
            (int) config('telescope.storage.database.chunk'),
        );

        /** @var Collection<int, IncomingEntry> $entries */
        $entries = collect($this->entries)->map(fn (array $entry): IncomingEntry => QueuedTelescopeEntry::fromPayload($entry, $this->batchId));

        Telescope::withoutRecording(fn () => $repository->store($entries));
    }
}
