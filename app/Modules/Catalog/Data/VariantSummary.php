<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Data;

use App\Modules\Catalog\Enums\StockStatus;

/**
 * A SKU as other modules show it in the admin (e.g. Pricing's SKU page): product, SKU title and code, prices in Rial,
 * stock and the commission it uses.
 */
final readonly class VariantSummary
{
    public function __construct(
        public int $variantId,
        public int $productId,
        public string $productName,
        public string $productRouteKey,
        public string $title,
        public string $sku,
        public ?string $barcode,
        public ?int $officialPrice,
        public int $price,
        public ?int $salePrice,
        public StockStatus $stockStatus,
        public ?float $commissionPercent,
        public ?string $commissionSource,
    ) {}
}
