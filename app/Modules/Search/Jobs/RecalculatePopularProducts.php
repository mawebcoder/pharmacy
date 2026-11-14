<?php

declare(strict_types=1);

namespace App\Modules\Search\Jobs;

use App\Modules\Catalog\Actions\ReplacePopularProducts;
use App\Modules\Search\Models\SearchLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * «محصولات محبوب» from search (owner, 2026-11-14), every night: products ranked by how often shoppers opened them
 * from search results over the last 30 days (search_logs.clicked_product_id), ties to the most recently opened; the
 * top 24 replace the system collection (ReplacePopularProducts), which the home page, the empty search box and
 * /collections/popular read. Nights without clicks keep the current list.
 */
final class RecalculatePopularProducts implements ShouldQueue
{
    use Queueable;

    public const int DAYS = 30;

    public const int LIMIT = 24;

    public function handle(ReplacePopularProducts $replacePopular): void
    {
        $productIds = SearchLog::query()
            ->whereNotNull(SearchLog::COLUMN_CLICKED_PRODUCT_ID)
            ->where(SearchLog::COLUMN_CREATED_AT, '>=', now()->subDays(self::DAYS))
            ->selectRaw(SearchLog::COLUMN_CLICKED_PRODUCT_ID.' as product_id, COUNT(*) as opens, MAX('.SearchLog::COLUMN_ID.') as last_open')
            ->groupBy(SearchLog::COLUMN_CLICKED_PRODUCT_ID)
            ->orderByDesc('opens')
            ->orderByDesc('last_open')
            ->limit(self::LIMIT)
            ->pluck('product_id')
            ->map(intval(...))
            ->values()
            ->all();

        $replacePopular(array_values($productIds));
    }
}
