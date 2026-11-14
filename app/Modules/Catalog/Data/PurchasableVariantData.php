<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Data;

use App\Modules\Media\Contracts\StoredImage;

/**
 * What the cart needs to know about one SKU now (roadmap D13.3, S19): current prices (rial), whether it can be
 * bought and why not, and its limit per order (SKU, else product, else the store setting).
 */
final readonly class PurchasableVariantData
{
    public const string REFUSED_UNAVAILABLE = 'unavailable';

    public const string REFUSED_OUT_OF_STOCK = 'out_of_stock';

    public const string REFUSED_PRESCRIPTION = 'prescription';

    public const string REFUSED_COLD_CHAIN = 'cold_chain';

    public function __construct(
        public int $variantId,
        public int $productId,
        public string $productName,
        public string $productSlug,
        public ?string $brandName,
        public string $variantTitle,
        public bool $hasOtherVariants,
        public string $sku,
        public int $price,
        public ?int $compareAtPrice,
        public int $maxPerOrder,
        public ?string $refusal,
        public ?StoredImage $image,
    ) {}

    public function canBuy(): bool
    {
        return $this->refusal === null;
    }

    /**
     * Why it cannot be bought, for the customer.
     */
    public function refusalMessage(): ?string
    {
        return match ($this->refusal) {
            self::REFUSED_OUT_OF_STOCK => 'این کالا فعلاً موجود نیست.',
            self::REFUSED_PRESCRIPTION => 'این دارو نسخه‌ای است و فروش آنلاین ندارد.',
            self::REFUSED_COLD_CHAIN => 'این کالا به زنجیره سرد نیاز دارد و فعلاً ارسال نمی‌شود.',
            self::REFUSED_UNAVAILABLE => 'این کالا دیگر فروخته نمی‌شود.',
            default => null,
        };
    }
}
