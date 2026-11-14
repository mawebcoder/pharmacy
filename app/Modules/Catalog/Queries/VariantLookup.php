<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Models\ProductVariant;

/**
 * Finds SKUs by their codes for imports in other modules (e.g. supplier price Excel, roadmap D13.9): by our SKU code
 * first, then by barcode. Trashed SKUs are not matched.
 */
final readonly class VariantLookup
{
    /**
     * @param  list<string>  $skus
     * @return array<string, int> upper-cased SKU code => variant id
     */
    public function idsBySku(array $skus): array
    {
        return $this->ids(ProductVariant::COLUMN_SKU, $skus);
    }

    /**
     * @param  list<string>  $barcodes
     * @return array<string, int> barcode => variant id (a barcode shared by several SKUs matches none)
     */
    public function idsByBarcode(array $barcodes): array
    {
        return $this->ids(ProductVariant::COLUMN_BARCODE, $barcodes);
    }

    /**
     * @param  list<string>  $values
     * @return array<string, int>
     */
    private function ids(string $column, array $values): array
    {
        $values = array_values(array_unique(array_filter($values, fn (string $value): bool => $value !== '')));

        if ($values === []) {
            return [];
        }

        $ids = [];
        $seen = [];

        foreach (ProductVariant::query()->whereIn($column, $values)->get([ProductVariant::COLUMN_ID, $column]) as $variant) {
            $key = mb_strtoupper((string) $variant->getAttribute($column));
            $seen[$key] = ($seen[$key] ?? 0) + 1;
            $ids[$key] = $variant->getId();
        }

        return array_filter($ids, fn (string $key): bool => $seen[$key] === 1, ARRAY_FILTER_USE_KEY);
    }
}
