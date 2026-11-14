<?php

declare(strict_types=1);

namespace App\Modules\Search\Queries;

use App\Modules\Search\Models\SearchLog;

/**
 * A signed-in customer's search history (owner, 2026-11-14): their latest distinct searches from search_logs, newest
 * first, as the search box shows them («جستجوهای اخیر»). Read only when the box opens, with «محصولات محبوب».
 */
final class RecentSearches
{
    public const int LIMIT = 6;

    /**
     * @return list<array{q: string, at: int}> the text as typed and when, in milliseconds (like the browser's list)
     */
    public function for(int $customerId, int $limit = self::LIMIT): array
    {
        $latestIds = SearchLog::query()
            ->where(SearchLog::COLUMN_CUSTOMER_ID, $customerId)
            ->selectRaw('MAX('.SearchLog::COLUMN_ID.') as latest_id')
            ->groupBy(SearchLog::COLUMN_NORMALIZED_QUERY)
            ->orderByDesc('latest_id')
            ->limit($limit)
            ->pluck('latest_id')
            ->all();

        return array_values(SearchLog::query()
            ->whereKey($latestIds)
            ->orderByDesc(SearchLog::COLUMN_ID)
            ->get()
            ->map(fn (SearchLog $log): array => ['q' => $log->getQuery(), 'at' => (int) $log->getCreatedAt()?->getTimestampMs()])
            ->all());
    }
}
