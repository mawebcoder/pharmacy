<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Data\VariantPriceUpdate;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Search\ProductSearchIndex;
use Illuminate\Support\Facades\DB;

/**
 * Copies new prices from Pricing onto the SKUs (roadmap D3.2): the selling price, the haraj and the latest record id,
 * and the stock that follows the suppliers. Only Pricing's job calls this; the admin never types a selling price.
 * The products' search documents follow (the default SKU may switch).
 */
final class ApplyVariantPrices
{
    /**
     * @param  list<VariantPriceUpdate>  $updates
     */
    public function __invoke(array $updates): void
    {
        if ($updates === []) {
            return;
        }

        $productIds = DB::transaction(function () use ($updates): array {
            $variants = ProductVariant::query()->withTrashed()->whereKey(array_map(fn (VariantPriceUpdate $update): int => $update->variantId, $updates))->get()->keyBy(ProductVariant::COLUMN_ID);
            $productIds = [];

            foreach ($updates as $update) {
                $variant = $variants->get($update->variantId);

                if (! $variant instanceof ProductVariant) {
                    continue;
                }

                $variant->setPrice($update->price)
                    ->setSalePrice($update->salePrice)
                    ->setCurrentPriceId($update->priceRecordId);

                if ($update->stockStatus !== null) {
                    $variant->setStockStatus($update->stockStatus);
                }

                $variant->save();
                $productIds[$variant->getProductId()] = true;
            }

            return array_keys($productIds);
        });

        ProductSearchIndex::refreshWhere(Product::query()->whereKey($productIds));
    }
}
