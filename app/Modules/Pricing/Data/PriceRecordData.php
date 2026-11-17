<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Data;

use Carbon\CarbonImmutable;

/**
 * A SKU's current price record (roadmap D3.2) as an order item copies it at checkout (D3.4 «Price snapshot»): the
 * supplier and its cost (null when sold at the printed price), the commission and where it came from, how the
 * supplier was chosen, and the printed, list and haraj prices. Money in rial; enums as their values.
 */
final readonly class PriceRecordData
{
    public function __construct(
        public int $variantPriceId,
        public ?int $providerId,
        public ?string $providerName,
        public ?int $providerCost,
        public ?string $providerChoice,
        public ?float $commissionPercent,
        public ?string $commissionSource,
        public ?int $officialPrice,
        public int $listPrice,
        public ?int $salePrice,
        public bool $isNearExpiry = false,
        public ?CarbonImmutable $expiryDate = null,
    ) {}
}
