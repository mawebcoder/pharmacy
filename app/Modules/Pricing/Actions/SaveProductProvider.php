<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Actions;

use App\Modules\Catalog\Queries\VariantPricingInputs;
use App\Modules\Pricing\Data\ProductProviderData;
use App\Modules\Pricing\Enums\PriceChangeReason;
use App\Modules\Pricing\Jobs\RecalculateVariantPrices;
use App\Modules\Pricing\Models\ProductProvider;
use Illuminate\Support\Facades\DB;

/**
 * Sets a supplier's commission for a product and whether it is the product's preferred supplier (roadmap D3.2,
 * S15b). One preferred supplier per product: choosing one clears the other. The product's SKUs are re-priced in the
 * background.
 */
final readonly class SaveProductProvider
{
    public function __construct(private VariantPricingInputs $variants) {}

    #[\NoDiscard]
    public function __invoke(ProductProviderData $data, ?ProductProvider $row = null, ?int $adminId = null): ProductProvider
    {
        return DB::transaction(function () use ($data, $row, $adminId): ProductProvider {
            $row ??= new ProductProvider;
            $row->setProductId($data->productId)
                ->setProviderId($data->providerId)
                ->setCommissionPercent($data->commissionPercent)
                ->setIsPreferred($data->isPreferred)
                ->save();

            if ($row->isPreferred()) {
                ProductProvider::query()
                    ->where(ProductProvider::COLUMN_PRODUCT_ID, $row->getProductId())
                    ->whereKeyNot($row->getId())
                    ->where(ProductProvider::COLUMN_IS_PREFERRED, true)
                    ->get()
                    ->each(fn (ProductProvider $other): bool => $other->setIsPreferred(false)->save());
            }

            RecalculateVariantPrices::dispatch($this->variants->variantIdsOfProduct($data->productId), PriceChangeReason::CommissionChanged, $adminId)->afterCommit();

            return $row;
        });
    }
}
