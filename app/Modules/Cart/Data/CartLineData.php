<?php

declare(strict_types=1);

namespace App\Modules\Cart\Data;

use App\Modules\Catalog\Data\PurchasableVariantData;

/**
 * One cart line as shown: the SKU now, its quantity and total at the current price (rial), the earlier price when it
 * has changed, and whether it can still be bought (unavailable lines are left out of the total).
 */
final readonly class CartLineData
{
    public function __construct(
        public PurchasableVariantData $variant,
        public int $quantity,
        public int $lineTotal,
        public ?int $previousPrice,
    ) {}

    public function isAvailable(): bool
    {
        return $this->variant->canBuy();
    }
}
