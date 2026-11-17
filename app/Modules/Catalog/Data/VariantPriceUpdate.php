<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Data;

use App\Modules\Catalog\Enums\StockStatus;
use Carbon\CarbonImmutable;

/**
 * A SKU's new price from Pricing (roadmap D3.2): the selling price and haraj of the latest record and its id, and
 * the stock that follows the suppliers (null leaves the stock as it is), and whether the selling supplier's stock is
 * «زودمصرف» with its expiry date. Money in Rial.
 */
final readonly class VariantPriceUpdate
{
    public function __construct(
        public int $variantId,
        public int $price,
        public ?int $salePrice,
        public int $priceRecordId,
        public ?StockStatus $stockStatus = null,
        public bool $isNearExpiry = false,
        public ?CarbonImmutable $expiryDate = null,
    ) {}
}
