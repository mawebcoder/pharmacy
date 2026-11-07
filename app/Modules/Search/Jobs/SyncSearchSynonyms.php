<?php

declare(strict_types=1);

namespace App\Modules\Search\Jobs;

use App\Modules\Catalog\Actions\ApplySearchSynonyms;
use App\Modules\Search\Models\SearchSynonym;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Copies all synonyms into the product search index (roadmap D21.5.6), on the "search" queue. Unique, so many
 * edits in a row send the list once.
 */
final class SyncSearchSynonyms implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct()
    {
        $this->onQueue('search');
    }

    public function handle(ApplySearchSynonyms $applySynonyms): void
    {
        if (config('scout.driver') !== 'meilisearch') {
            return;
        }

        $applySynonyms(array_values(SearchSynonym::query()->get()
            ->map(fn (SearchSynonym $synonym): array => [$synonym->getTerm(), ...$synonym->getSynonyms()])
            ->all()));
    }
}
