<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Jobs;

use App\Modules\Catalog\Events\ProductsSearchFieldsChanged;
use App\Modules\Catalog\Models\Product;
use App\Modules\Orders\Queries\ProductSales;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Nightly popularity (roadmap S39, owner 2026-11-27): each product's units in paid orders of the last 30 days into
 * products.sales_30d. Only products whose count changed are saved and re-indexed, so search ranks and «پرفروش‌ترین»
 * sorts by it. Scheduled in CatalogServiceProvider.
 */
final class RecalculateProductSales implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public const int DAYS = 30;

    public function __construct()
    {
        // With the other search work, so the default queue's order and SMS jobs never wait behind it.
        $this->onQueue('search');
    }

    public function handle(ProductSales $sales): void
    {
        $units = $sales->unitsSince(now()->subDays(self::DAYS));
        $current = array_map(intval(...), Product::withTrashed()
            ->where(fn ($query) => $query->where(Product::COLUMN_SALES_30D, '>', 0)->orWhereKey(array_keys($units)))
            ->pluck(Product::COLUMN_SALES_30D, Product::COLUMN_ID)
            ->all());
        $changed = [];

        foreach ($current as $productId => $was) {
            $now = $units[$productId] ?? 0;

            if ($now !== $was) {
                Product::withTrashed()->whereKey($productId)->update([Product::COLUMN_SALES_30D => $now]);
                $changed[] = (int) $productId;
            }
        }

        if ($changed !== []) {
            ProductsSearchFieldsChanged::dispatch($changed);
        }
    }
}
