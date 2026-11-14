<?php

declare(strict_types=1);

namespace App\Modules\Search\Actions;

use App\Modules\Search\Models\SearchLog;

/**
 * «پاک کردن» of the search history (owner, 2026-11-14): the customer's searches stay in the insights but are no longer
 * linked to them, so they leave their history on every device.
 */
final class ForgetRecentSearches
{
    public function __invoke(int $customerId): void
    {
        SearchLog::query()->where(SearchLog::COLUMN_CUSTOMER_ID, $customerId)->update([SearchLog::COLUMN_CUSTOMER_ID => null]);
    }
}
