<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Actions;

use App\Modules\Pricing\Enums\PriceChangeReason;
use App\Modules\Pricing\Enums\ProviderPriceEvent;
use App\Modules\Pricing\Jobs\RecalculateVariantPrices;
use App\Modules\Pricing\Models\ProviderPrice;
use Illuminate\Support\Facades\DB;

/**
 * Removes a supplier from a SKU (kept in the history) and re-prices the SKU in the background.
 */
final class DeleteProviderPrice
{
    public function __invoke(ProviderPrice $price, ?int $adminId = null): void
    {
        DB::transaction(function () use ($price, $adminId): void {
            SaveProviderPrice::recordChange($price, ProviderPriceEvent::Deleted, $adminId);
            $price->delete();
            RecalculateVariantPrices::dispatch([$price->getVariantId()], PriceChangeReason::ProviderPriceChanged, $adminId)->afterCommit();
        });
    }
}
