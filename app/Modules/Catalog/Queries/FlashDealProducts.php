<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Models\FlashDeal;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Products with a live flash deal (roadmap S33) for the home widget and /deals: sellable and in stock, the deal ending
 * soonest first, one card per product. The SKU's price is already the deal price (SyncFlashDealPrices).
 */
final readonly class FlashDealProducts
{
    /**
     * @return Collection<int, Product>
     */
    public function live(?int $limit = null): Collection
    {
        $deals = FlashDeal::query()->live()->orderBy(FlashDeal::COLUMN_ENDS_AT)->with('variant')->get();
        $productIds = $deals->map(fn (FlashDeal $deal): ?int => $deal->getVariant()?->getProductId())->filter()->unique()->values();

        if ($productIds->isEmpty()) {
            return new Collection;
        }

        $products = Product::query()
            ->active()
            ->whereKey($productIds->all())
            ->with(ProductListing::CARD_RELATIONS)
            ->get()
            ->filter(fn (Product $product): bool => $product->isSellableType())
            ->keyBy(fn (Product $product): int => $product->getId());

        // The card shows the deal's SKU even when it is not the product's default (in memory only, never saved).
        foreach ($deals as $deal) {
            $products->get((int) $deal->getVariant()?->getProductId())?->setDefaultVariantId($deal->getVariantId());
        }

        $ordered = $productIds
            ->map(fn (int $id): ?Product => $products->get($id))
            ->filter(fn (?Product $product): bool => $product?->getDefaultVariant()?->isInStock() ?? false)
            ->values();

        return new Collection($limit === null ? $ordered->all() : $ordered->take($limit)->all());
    }

    /**
     * When the last live deal ends: the one countdown the home row and /deals show (owner, 2026-11-17: each SKU has
     * its own window, the countdown runs to the latest end). Null without a live deal.
     */
    public function countdownEnd(): ?Carbon
    {
        $end = FlashDeal::query()->live()->max(FlashDeal::COLUMN_ENDS_AT);

        return $end === null ? null : Carbon::parse((string) $end);
    }
}
