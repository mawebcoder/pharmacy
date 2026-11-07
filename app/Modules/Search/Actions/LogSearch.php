<?php

declare(strict_types=1);

namespace App\Modules\Search\Actions;

use App\Modules\Search\Models\SearchLog;
use App\Modules\Shared\Support\SearchText;

/**
 * Records a search from the search page (roadmap D21.4.4 step 6) for the insights page.
 */
final class LogSearch
{
    #[\NoDiscard]
    public function __invoke(string $query, int $resultsCount): SearchLog
    {
        $log = (new SearchLog)
            ->setQuery(trim($query))
            ->setNormalizedQuery(SearchText::normalize($query))
            ->setResultsCount($resultsCount);
        $log->save();

        return $log;
    }
}
