<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Data;

use App\Modules\Shipping\Enums\TipaxParcelStage;

/**
 * Where a Tipax registration stands now (roadmap S27): its parcels' stage together and Tipax's status titles.
 */
final readonly class TipaxShipmentProgressData
{
    public function __construct(
        public TipaxParcelStage $stage,
        public ?string $status,
    ) {}
}
