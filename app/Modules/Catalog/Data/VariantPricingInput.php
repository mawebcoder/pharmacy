<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Data;

use App\Modules\Catalog\Enums\StockStatus;

/**
 * What Pricing needs to price one SKU (roadmap D3.2): the admin's printed price and haraj, the current selling price
 * and record, the general commission % (the product's, else the brand's) with its source, and both on their own
 * with the brand id, for the per-supplier levels (S15b). Money in Rial.
 */
final readonly class VariantPricingInput
{
    public function __construct(
        public int $variantId,
        public int $productId,
        public ?int $officialPrice,
        public ?int $salePrice,
        public int $price,
        public ?int $currentPriceId,
        public ?float $commissionPercent,
        public ?string $commissionSource,
        public StockStatus $stockStatus,
        public ?int $brandId = null,
        public ?float $productCommission = null,
        public ?float $brandCommission = null,
    ) {}
}
