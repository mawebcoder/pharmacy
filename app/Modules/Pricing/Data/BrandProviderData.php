<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Data;

/**
 * Input for a supplier's commission on a brand.
 */
final readonly class BrandProviderData
{
    public function __construct(
        public int $brandId,
        public int $providerId,
        public float $commissionPercent,
    ) {}
}
