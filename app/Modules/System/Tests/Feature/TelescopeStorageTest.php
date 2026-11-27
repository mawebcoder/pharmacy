<?php

declare(strict_types=1);

use App\Modules\System\Jobs\WriteTelescopeEntries;
use App\Modules\System\Support\QueuedTelescopeRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\IncomingExceptionEntry;

it('queues Telescope entries instead of writing them during the request', function (): void {
    Queue::fake();

    $repository = app(EntriesRepository::class);
    $repository->store(collect([IncomingEntry::make(['uri' => '/'])->type(EntryType::REQUEST)->batchId('batch-1')]));

    expect($repository)->toBeInstanceOf(QueuedTelescopeRepository::class)
        ->and(DB::table('telescope_entries')->count())->toBe(0);
    Queue::assertPushed(WriteTelescopeEntries::class, fn (WriteTelescopeEntries $job): bool => $job->batchId === 'batch-1' && count($job->entries) === 1);
});

it('writes the queued entries with their tags, and exceptions with their family and occurrences', function (): void {
    $request = IncomingEntry::make(['uri' => '/products', 'duration' => 2100])->type(EntryType::REQUEST)->batchId('batch-2')->tags(['slow']);
    $exception = new IncomingExceptionEntry(new RuntimeException('شکست'), ['class' => RuntimeException::class, 'file' => 'app/x.php', 'line' => 12, 'message' => 'شکست']);
    $exception->type(EntryType::EXCEPTION)->batchId('batch-2');

    app(EntriesRepository::class)->store(collect([$request, $exception]));

    expect(DB::table('telescope_entries')->where('type', EntryType::REQUEST)->value('uuid'))->toBe($request->uuid)
        ->and(DB::table('telescope_entries_tags')->where('entry_uuid', $request->uuid)->value('tag'))->toBe('slow');

    $stored = DB::table('telescope_entries')->where('type', EntryType::EXCEPTION)->first();
    expect($stored->family_hash)->toBe(md5('app/x.php12'))
        ->and($stored->batch_id)->toBe('batch-2')
        ->and(json_decode($stored->content, true)['occurrences'])->toBe(1);
});
