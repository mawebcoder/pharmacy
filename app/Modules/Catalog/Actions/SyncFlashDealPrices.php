<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\FlashDeal;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Search\ProductSearchIndex;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Puts each live flash deal's price on its SKU and clears it from SKUs whose deal ended, sold out or was switched
 * off (roadmap S33). Runs every minute (SyncFlashDeals) and after every deal change, reservation or release; the
 * products whose SKUs changed are re-indexed so search and cards show the new price. Pass SKU ids to sync only
 * those.
 */
final readonly class SyncFlashDealPrices
{
    /**
     * @param  list<int>|null  $variantIds
     */
    public function __invoke(?array $variantIds = null): void
    {
        $live = FlashDeal::query()
            ->live()
            ->when($variantIds !== null, fn (Builder $query): Builder => $query->whereIn(FlashDeal::COLUMN_VARIANT_ID, $variantIds))
            ->orderBy(FlashDeal::COLUMN_DEAL_PRICE)
            ->get()
            ->unique(fn (FlashDeal $deal): int => $deal->getVariantId())
            ->keyBy(fn (FlashDeal $deal): int => $deal->getVariantId());

        $variants = ProductVariant::query()
            ->with('product')
            ->where(fn (Builder $query): Builder => $query
                ->whereNotNull(ProductVariant::COLUMN_FLASH_DEAL_ID)
                ->orWhereIn('id', $live->keys()->all()))
            ->when($variantIds !== null, fn (Builder $query): Builder => $query->whereIn('id', $variantIds))
            ->get();

        $changed = [];

        DB::transaction(function () use ($variants, $live, &$changed): void {
            foreach ($variants as $variant) {
                /** @var ProductVariant $variant */
                $deal = $live->get($variant->getId());
                $dealId = $deal instanceof FlashDeal ? $deal->getId() : null;
                $dealPrice = $deal instanceof FlashDeal ? $deal->getDealPrice() : null;

                if ($variant->getFlashDealId() === $dealId && $variant->getFlashDealPrice() === $dealPrice) {
                    continue;
                }

                $variant->setFlashDeal($dealId, $dealPrice)->saveQuietly();
                $changed[$variant->getProductId()] = $variant->getProduct();
            }
        });

        foreach ($changed as $product) {
            if ($product !== null) {
                ProductSearchIndex::refresh($product->unsetRelation('variants'));
            }
        }
    }
}
