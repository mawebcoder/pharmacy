<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Listeners;

use App\Modules\Catalog\Enums\PricingInputChange;
use App\Modules\Catalog\Events\PricingInputsChanged;
use App\Modules\Pricing\Enums\PriceChangeReason;
use App\Modules\Pricing\Jobs\RecalculateVariantPrices;

/**
 * A printed price, haraj or commission changed in Catalog: re-price those SKUs in the background (roadmap D3.2).
 */
final class QueuePriceRecalculation
{
    public function handle(PricingInputsChanged $event): void
    {
        if ($event->variantIds === []) {
            return;
        }

        RecalculateVariantPrices::dispatch($event->variantIds, match ($event->change) {
            PricingInputChange::Created => PriceChangeReason::Initial,
            PricingInputChange::Printed => PriceChangeReason::PrintedChanged,
            PricingInputChange::Haraj => PriceChangeReason::HarajChanged,
            PricingInputChange::Commission => PriceChangeReason::CommissionChanged,
        }, $event->adminId)->afterCommit();
    }
}
