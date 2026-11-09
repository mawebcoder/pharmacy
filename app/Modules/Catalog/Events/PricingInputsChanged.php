<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Events;

use App\Modules\Catalog\Enums\PricingInputChange;

/**
 * A printed price, haraj or commission changed for these SKUs (roadmap D3.2, C3 rule 2): Pricing listens and
 * recalculates their prices in the background.
 */
final readonly class PricingInputsChanged
{
    /**
     * @param  list<int>  $variantIds
     */
    public function __construct(
        public array $variantIds,
        public PricingInputChange $change,
        public ?int $adminId = null,
    ) {}
}
