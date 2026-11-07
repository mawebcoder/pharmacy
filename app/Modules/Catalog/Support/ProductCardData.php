<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Enums\StockStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Shared\Support\Toman;

/**
 * What a product card shows (roadmap B9.4): the cheapest active variant's price (with the old price when on sale),
 * stock, and at most one badge in priority order: discount % → near expiry → damaged box → new.
 * Needs the product's active variants loaded.
 */
final readonly class ProductCardData
{
    public const int NEW_FOR_DAYS = 30;

    public function __construct(
        public ?int $price,
        public ?int $oldPrice,
        public bool $inStock,
        public ?string $badge,
        public ?string $badgeTone,
    ) {}

    public static function for(Product $product): self
    {
        $variants = $product->getVariants()->filter(fn (ProductVariant $variant): bool => $variant->isActive());
        $cheapest = $variants->sortBy(fn (ProductVariant $variant): int => $variant->currentPrice())->first();
        $discount = $cheapest?->getSalePrice() === null ? 0 : Toman::discountPercent($cheapest->getPrice(), (int) $cheapest->getSalePrice());

        [$badge, $tone] = match (true) {
            $discount > 0 => [null, 'discount'],
            $variants->contains(fn (ProductVariant $variant): bool => $variant->isNearExpiry()) => ['زودمصرف', 'near-expiry'],
            $variants->contains(fn (ProductVariant $variant): bool => $variant->isDamagedBox()) => ['کج‌جعبه', 'damaged-box'],
            ($product->getCreatedAt()?->diffInDays(now()) ?? PHP_INT_MAX) <= self::NEW_FOR_DAYS => ['جدید', 'new'],
            default => [null, null],
        };

        return new self(
            price: $cheapest?->currentPrice(),
            oldPrice: $cheapest?->getSalePrice() === null ? null : $cheapest->getPrice(),
            inStock: $variants->contains(fn (ProductVariant $variant): bool => $variant->getStockStatus() !== StockStatus::OutOfStock),
            badge: $badge,
            badgeTone: $tone,
        );
    }
}
