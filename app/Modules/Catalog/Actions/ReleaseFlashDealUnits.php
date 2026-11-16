<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Jobs\SyncFlashDeals;
use App\Modules\Catalog\Models\FlashDeal;
use Illuminate\Support\Facades\DB;

/**
 * Gives a flash deal back the units of an order that expired unpaid or was cancelled (roadmap S33): a sold-out deal
 * that is still in its window goes live again (in the background).
 */
final readonly class ReleaseFlashDealUnits
{
    public function __invoke(int $dealId, int $quantity): void
    {
        $deal = DB::transaction(function () use ($dealId, $quantity): ?FlashDeal {
            $deal = FlashDeal::query()->lockForUpdate()->find($dealId);
            $deal?->setSoldCount(max(0, $deal->getSoldCount() - $quantity))->saveQuietly();

            return $deal;
        });

        if ($deal instanceof FlashDeal) {
            SyncFlashDeals::dispatch([$deal->getVariantId()])->afterCommit();
        }
    }
}
