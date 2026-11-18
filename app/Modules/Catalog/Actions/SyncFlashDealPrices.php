<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\FlashDeal;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Search\ProductSearchIndex;
use App\Modules\Pricing\Data\PriceRecordData;
use App\Modules\Pricing\Queries\CurrentPriceRecords;
use App\Modules\Pricing\Queries\SupplierOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Puts each live flash deal's price on its SKU and clears it from SKUs whose deal ended, sold out or was switched
 * off (roadmap S28), with the deal supplier's «زودمصرف» and expiry date while it runs (owner, 2026-11-17). Runs
 * every minute (SyncFlashDeals) and after every deal change, reservation or release; the products whose SKUs changed
 * are re-indexed so search and cards show the new price. Pass SKU ids to sync only those.
 */
final readonly class SyncFlashDealPrices
{
    public function __construct(
        private SupplierOptions $suppliers,
        private CurrentPriceRecords $priceRecords,
    ) {}

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

                // «زودمصرف» follows the deal's supplier while it runs, else the selling supplier of the latest price record.
                if ($deal instanceof FlashDeal && $deal->getProviderId() !== null) {
                    [$nearExpiry, $expiryDate] = $this->suppliers->nearExpiry($deal->getProviderId(), $variant->getId());
                } else {
                    $record = $this->priceRecords->for([$variant->getId()])[$variant->getId()] ?? null;
                    $nearExpiry = $record instanceof PriceRecordData && $record->isNearExpiry;
                    $expiryDate = $record instanceof PriceRecordData ? $record->expiryDate : null;
                }

                if ($variant->getFlashDealId() === $dealId && $variant->getFlashDealPrice() === $dealPrice
                    && $variant->isNearExpiry() === $nearExpiry && $variant->getExpiryDate()?->toDateString() === $expiryDate?->toDateString()) {
                    continue;
                }

                $variant->setFlashDeal($dealId, $dealPrice)->setNearExpiry($nearExpiry, $expiryDate)->saveQuietly();
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
