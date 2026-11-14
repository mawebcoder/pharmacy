<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Data;

use App\Modules\Pricing\Enums\ProviderChoice;

/**
 * The supplier the website sells a SKU from, and how it was chosen (roadmap D3.2, S15b).
 */
final readonly class ChosenSupplier
{
    public function __construct(
        public SupplierOffer $offer,
        public ProviderChoice $choice,
    ) {}
}
