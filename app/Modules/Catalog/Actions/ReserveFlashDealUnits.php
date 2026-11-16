<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Exceptions\FlashDealRefused;
use App\Modules\Catalog\Models\FlashDeal;
use Illuminate\Support\Facades\DB;

/**
 * Counts units against a flash deal when an order is placed (roadmap S33, owner 2026-11-16), locked so two buyers can
 * never take the same last units: refused once the deal ended or the units would pass its limit. When the limit is
 * reached the SKU goes back to its regular price. `force` counts the units anyway: a late payment for an expired
 * order whose units were released.
 */
final readonly class ReserveFlashDealUnits
{
    public function __construct(private SyncFlashDealPrices $syncPrices) {}

    /**
     * @throws FlashDealRefused
     */
    public function __invoke(int $dealId, int $quantity, string $productName, bool $force = false): void
    {
        $deal = DB::transaction(function () use ($dealId, $quantity, $productName, $force): ?FlashDeal {
            $deal = FlashDeal::query()->lockForUpdate()->find($dealId);

            if (! $deal instanceof FlashDeal) {
                if ($force) {
                    return null;
                }

                throw FlashDealRefused::soldOut($productName);
            }

            $fits = $deal->getQuantityLimit() === null || $deal->getSoldCount() + $quantity <= $deal->getQuantityLimit();

            if (! $force && (! $deal->isLive() || ! $fits)) {
                throw FlashDealRefused::soldOut($productName);
            }

            $deal->setSoldCount($deal->getSoldCount() + $quantity)->saveQuietly();

            return $deal;
        });

        if ($deal instanceof FlashDeal && $deal->isSoldOut()) {
            ($this->syncPrices)([$deal->getVariantId()]);
        }
    }
}
