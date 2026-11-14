<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Data;

use App\Modules\Pricing\Enums\CommissionSource;
use App\Modules\Pricing\Models\ProviderPrice;

/**
 * One supplier's offer for a SKU (roadmap D3.2, S15b): its row, the commission that applies to it and from which
 * level, the selling price it gives (rounded, capped by the printed price; null without a commission) and whether
 * the website can sell from it now.
 */
final readonly class SupplierOffer
{
    public function __construct(
        public ProviderPrice $price,
        public ?float $commissionPercent,
        public ?CommissionSource $commissionSource,
        public ?int $sellingPrice,
        public bool $cappedByPrinted,
        public bool $usable,
    ) {}
}
