<?php

declare(strict_types=1);

namespace App\Modules\System\Support;

use App\Modules\System\Jobs\WriteTelescopeEntries;
use Illuminate\Support\Collection;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Storage\DatabaseEntriesRepository;

/**
 * Telescope's storage with the writes off the request path (owner, 2026-11-27): a request or job only queues its
 * entries; WriteTelescopeEntries inserts them. Reading, updates, pruning and clearing stay Telescope's own. Updates to
 * an entry not written yet are retried by Telescope's ProcessPendingUpdates.
 */
final class QueuedTelescopeRepository extends DatabaseEntriesRepository
{
    /**
     * @param  Collection<int, IncomingEntry>  $entries
     */
    public function store(Collection $entries): void
    {
        if ($entries->isEmpty()) {
            return;
        }

        WriteTelescopeEntries::dispatch(
            (string) $entries->first()->batchId,
            array_values($entries->map(QueuedTelescopeEntry::payload(...))->all()),
        );
    }
}
