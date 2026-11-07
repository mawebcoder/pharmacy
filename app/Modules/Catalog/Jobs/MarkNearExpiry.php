<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Jobs;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Support\NearExpiry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Daily (roadmap D9): variants whose expiry date came within the near-expiry window are flagged "زودمصرف", and the
 * flag is cleared for those whose date moved out of it (e.g. a corrected date). Saved one by one so changes are
 * audited.
 */
final class MarkNearExpiry implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $cutoff = NearExpiry::cutoff()->toDateString();

        ProductVariant::query()
            ->where(ProductVariant::COLUMN_IS_NEAR_EXPIRY, false)
            ->whereNotNull(ProductVariant::COLUMN_EXPIRY_DATE)
            ->whereDate(ProductVariant::COLUMN_EXPIRY_DATE, '<=', $cutoff)
            ->each(fn (ProductVariant $variant): bool => $variant->setIsNearExpiry(true)->save());

        ProductVariant::query()
            ->where(ProductVariant::COLUMN_IS_NEAR_EXPIRY, true)
            ->where(fn ($query) => $query
                ->whereNull(ProductVariant::COLUMN_EXPIRY_DATE)
                ->orWhereDate(ProductVariant::COLUMN_EXPIRY_DATE, '>', $cutoff))
            ->each(fn (ProductVariant $variant): bool => $variant->setIsNearExpiry(false)->save());
    }
}
