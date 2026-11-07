<?php

declare(strict_types=1);

namespace App\Modules\Search\Actions;

use App\Modules\Search\Jobs\SyncSearchSynonyms;
use App\Modules\Search\Models\SearchSynonym;

/**
 * Deletes a synonym group and sends the remaining synonyms to the search index.
 */
final class DeleteSearchSynonym
{
    public function __invoke(SearchSynonym $synonym): void
    {
        $synonym->delete();

        SyncSearchSynonyms::dispatch()->afterCommit();
    }
}
