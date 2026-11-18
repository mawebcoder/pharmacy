<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;

/**
 * What a product card shows (roadmap B9.4): the default SKU (Product::getDefaultVariant()) with its price, the
 * struck-through price and stock, and one corner badge: «زودمصرف» whenever the product is flagged (owner, 2026-11-17:
 * always, with a line saying why it is discounted), else «جدید» for a new product without a discount.
 * $variantId is the SKU «افزودن به سبد» adds (S21). Needs the product's active variants loaded.
 */
final readonly class ProductCardData
{
    public const int NEW_FOR_DAYS = 30;

    public function __construct(
        public ?int $variantId,
        public ?int $price,
        public ?int $oldPrice,
        public bool $inStock,
        public ?string $badge,
        public ?string $badgeTone,
        public bool $nearExpiry = false,
    ) {}

    public static function for(Product $product): self
    {
        $variants = $product->getVariants()->filter(fn (ProductVariant $variant): bool => $variant->isActive());
        $default = $product->getDefaultVariant();

        [$badge, $tone] = match (true) {
            $product->isNearExpiry() => ['زودمصرف', 'near-expiry'],
            ($default?->discountPercent() ?? 0) > 0 => [null, 'discount'],
            ($product->getCreatedAt()?->diffInDays(now()) ?? PHP_INT_MAX) <= self::NEW_FOR_DAYS => ['جدید', 'new'],
            default => [null, null],
        };

        return new self(
            variantId: $default?->getId(),
            price: $default?->currentPrice(),
            oldPrice: $default?->compareAtPrice(),
            inStock: $default?->isInStock() ?? false,
            badge: $badge,
            badgeTone: $tone,
            nearExpiry: $product->isNearExpiry(),
        );
    }
}
