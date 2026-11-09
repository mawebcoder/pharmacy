<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Data;

use App\Modules\Pricing\Enums\CommissionSource;

/**
 * The result of pricing one SKU (roadmap D3.2): what a new price record would hold. Money in Rial.
 */
final readonly class CalculatedPrice
{
    /**
     * @param  list<string>  $flags  VariantPrice::FLAG_*
     */
    public function __construct(
        public ?int $officialPrice,
        public ?int $providerId,
        public ?int $providerCost,
        public ?float $commissionPercent,
        public ?CommissionSource $commissionSource,
        public int $price,
        public ?int $salePrice,
        public array $flags,
    ) {}
}
