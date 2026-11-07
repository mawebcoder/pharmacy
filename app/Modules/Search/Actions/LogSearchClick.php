<?php

declare(strict_types=1);

namespace App\Modules\Search\Actions;

use App\Modules\Search\Models\SearchLog;

/**
 * Records which product a shopper opened from a search's results (the first click counts).
 */
final class LogSearchClick
{
    public function __invoke(int $searchLogId, int $productId): void
    {
        $log = SearchLog::query()->find($searchLogId);

        if ($log instanceof SearchLog && $log->getClickedProductId() === null) {
            $log->setClickedProductId($productId)->save();
        }
    }
}
