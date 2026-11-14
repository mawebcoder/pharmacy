<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Actions;

use App\Modules\Catalog\Queries\VariantPricingInputs;
use App\Modules\Pricing\Enums\PriceChangeReason;
use App\Modules\Pricing\Jobs\RecalculateVariantPrices;
use App\Modules\Pricing\Models\ProductProvider;

/**
 * Removes a supplier's settings from a product (its commission and preferred flag); the SKUs are re-priced.
 */
final readonly class DeleteProductProvider
{
    public function __construct(private VariantPricingInputs $variants) {}

    public function __invoke(ProductProvider $row, ?int $adminId = null): void
    {
        $row->delete();
        RecalculateVariantPrices::dispatch($this->variants->variantIdsOfProduct($row->getProductId()), PriceChangeReason::CommissionChanged, $adminId)->afterCommit();
    }
}
