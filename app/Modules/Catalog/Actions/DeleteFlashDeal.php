<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Exceptions\FlashDealRefused;
use App\Modules\Catalog\Models\FlashDeal;

/**
 * Deletes a flash deal that sold nothing (roadmap S33); one with sales is switched off instead, so orders keep
 * pointing at it. The SKU's price follows at once.
 */
final readonly class DeleteFlashDeal
{
    public function __construct(private SyncFlashDealPrices $syncPrices) {}

    /**
     * @throws FlashDealRefused
     */
    public function __invoke(FlashDeal $deal): void
    {
        if ($deal->getSoldCount() > 0) {
            throw FlashDealRefused::alreadySold();
        }

        $variantId = $deal->getVariantId();
        $deal->delete();

        ($this->syncPrices)([$variantId]);
    }
}
