<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Data;

/**
 * Input for a supplier's settings on a product: its commission for this product (optional) and whether it is the
 * product's preferred supplier.
 */
final readonly class ProductProviderData
{
    public function __construct(
        public int $productId,
        public int $providerId,
        public ?float $commissionPercent,
        public bool $isPreferred,
    ) {}
}
