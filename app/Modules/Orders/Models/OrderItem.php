<?php

declare(strict_types=1);

namespace App\Modules\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One line of an order (roadmap D3.4 «Snapshot rule», «Price snapshot»): the product's names, SKU, brand and card
 * image, and the price record of that moment (supplier and cost, commission and its source, printed, list, haraj
 * and paid price), plus its share of the coupon discount. Items have no status of their own (owner, 2026-11-14): the
 * order's status covers them.
 *
 * Money per unit and for the item (owner, 2026-11-16, for returns): the haraj discount (list price − unit price), the
 * coupon discount and what the customer paid. A coupon share does not always split evenly over the units, so the
 * per-unit paid price is rounded down (a return never refunds more than was paid), the per-unit coupon discount is
 * the rest of the unit price, and the item totals stay exact. Returning n units refunds n × paid_unit, and the last
 * units returned also take the remainder (paid_total − paid_unit × quantity, under quantity rial), so an item's
 * returns add up to what was paid for it. An order line, not an admin record: not in the audit log.
 */
final class OrderItem extends Model
{
    public const string COLUMN_ID = 'id';

    public const string COLUMN_ORDER_ID = 'order_id';

    public const string COLUMN_VARIANT_ID = 'variant_id';

    public const string COLUMN_PRODUCT_ID = 'product_id';

    public const string COLUMN_VARIANT_PRICE_ID = 'variant_price_id';

    public const string COLUMN_PRODUCT_NAME = 'product_name';

    public const string COLUMN_VARIANT_TITLE = 'variant_title';

    public const string COLUMN_SKU = 'sku';

    public const string COLUMN_BRAND_NAME = 'brand_name';

    public const string COLUMN_IMAGE_URL = 'image_url';

    public const string COLUMN_PRODUCT_TYPE = 'product_type';

    public const string COLUMN_REQUIRES_PRESCRIPTION = 'requires_prescription';

    public const string COLUMN_FLASH_DEAL_ID = 'flash_deal_id';

    public const string COLUMN_QUANTITY = 'quantity';

    public const string COLUMN_PRODUCT_SNAPSHOT = 'product_snapshot';

    public const string COLUMN_PROVIDER_ID = 'provider_id';

    public const string COLUMN_PROVIDER_NAME = 'provider_name';

    public const string COLUMN_UNIT_COST = 'unit_cost';

    public const string COLUMN_COMMISSION_PERCENT = 'commission_percent';

    public const string COLUMN_COMMISSION_SOURCE = 'commission_source';

    public const string COLUMN_PROVIDER_CHOICE = 'provider_choice';

    public const string COLUMN_OFFICIAL_PRICE = 'official_price';

    public const string COLUMN_LIST_PRICE = 'list_price';

    public const string COLUMN_SALE_PRICE = 'sale_price';

    public const string COLUMN_UNIT_PRICE = 'unit_price';

    public const string COLUMN_HARAJ_DISCOUNT_UNIT = 'haraj_discount_unit';

    public const string COLUMN_HARAJ_DISCOUNT_TOTAL = 'haraj_discount_total';

    public const string COLUMN_COUPON_DISCOUNT_UNIT = 'coupon_discount_unit';

    public const string COLUMN_COUPON_DISCOUNT_TOTAL = 'coupon_discount_total';

    public const string COLUMN_PAID_UNIT = 'paid_unit';

    public const string COLUMN_PAID_TOTAL = 'paid_total';

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getOrderId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ORDER_ID);
    }

    public function setOrderId(int $value): static
    {
        return $this->setAttribute(self::COLUMN_ORDER_ID, $value);
    }

    public function getVariantId(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_VARIANT_ID);

        return $value === null ? null : (int) $value;
    }

    public function setVariantId(?int $value): static
    {
        return $this->setAttribute(self::COLUMN_VARIANT_ID, $value);
    }

    public function getProductId(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_PRODUCT_ID);

        return $value === null ? null : (int) $value;
    }

    public function setProductId(?int $value): static
    {
        return $this->setAttribute(self::COLUMN_PRODUCT_ID, $value);
    }

    public function getVariantPriceId(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_VARIANT_PRICE_ID);

        return $value === null ? null : (int) $value;
    }

    public function setVariantPriceId(?int $value): static
    {
        return $this->setAttribute(self::COLUMN_VARIANT_PRICE_ID, $value);
    }

    public function getProductName(): string
    {
        return (string) $this->getAttribute(self::COLUMN_PRODUCT_NAME);
    }

    public function setProductName(string $value): static
    {
        return $this->setAttribute(self::COLUMN_PRODUCT_NAME, $value);
    }

    public function getVariantTitle(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_VARIANT_TITLE);

        return $value === null ? null : (string) $value;
    }

    public function setVariantTitle(?string $value): static
    {
        return $this->setAttribute(self::COLUMN_VARIANT_TITLE, $value);
    }

    public function getSku(): string
    {
        return (string) $this->getAttribute(self::COLUMN_SKU);
    }

    public function setSku(string $value): static
    {
        return $this->setAttribute(self::COLUMN_SKU, $value);
    }

    public function getBrandName(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_BRAND_NAME);

        return $value === null ? null : (string) $value;
    }

    public function setBrandName(?string $value): static
    {
        return $this->setAttribute(self::COLUMN_BRAND_NAME, $value);
    }

    public function getImageUrl(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_IMAGE_URL);

        return $value === null ? null : (string) $value;
    }

    /**
     * The AVIF copy of the snapshot photo, offered before the WebP in a <picture>: product photos have always had AVIF
     * files of the same width beside the WebP ("…-product-320-webp.webp" → "…-product-320-avif.avif"). Null for any
     * other URL.
     */
    public function getImageAvifUrl(): ?string
    {
        $url = $this->getImageUrl();

        return $url !== null && preg_match('#-product-\d+-webp\.webp$#', $url) === 1
            ? (string) preg_replace('#-webp\.webp$#', '-avif.avif', $url)
            : null;
    }

    public function setImageUrl(?string $value): static
    {
        return $this->setAttribute(self::COLUMN_IMAGE_URL, $value);
    }

    public function getProductType(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_PRODUCT_TYPE);

        return $value === null ? null : (string) $value;
    }

    public function setProductType(?string $value): static
    {
        return $this->setAttribute(self::COLUMN_PRODUCT_TYPE, $value);
    }

    public function requiresPrescription(): bool
    {
        return (bool) $this->getAttribute(self::COLUMN_REQUIRES_PRESCRIPTION);
    }

    public function setRequiresPrescription(bool $value): static
    {
        return $this->setAttribute(self::COLUMN_REQUIRES_PRESCRIPTION, $value);
    }

    /**
     * The flash deal it was bought under (S28); its units are released when the order expires or is cancelled.
     */
    public function getFlashDealId(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_FLASH_DEAL_ID);

        return $value === null ? null : (int) $value;
    }

    public function setFlashDealId(?int $value): static
    {
        return $this->setAttribute(self::COLUMN_FLASH_DEAL_ID, $value);
    }

    public function getQuantity(): int
    {
        return (int) $this->getAttribute(self::COLUMN_QUANTITY);
    }

    public function setQuantity(int $value): static
    {
        return $this->setAttribute(self::COLUMN_QUANTITY, $value);
    }

    /**
     * The product and SKU as they were when the order was placed (Catalog's OrderItemSnapshots).
     *
     * @return array{product?: array<string, mixed>, sku?: array<string, mixed>}
     */
    public function getProductSnapshot(): array
    {
        return (array) $this->getAttribute(self::COLUMN_PRODUCT_SNAPSHOT);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    public function setProductSnapshot(array $value): static
    {
        return $this->setAttribute(self::COLUMN_PRODUCT_SNAPSHOT, $value);
    }

    public function getProviderName(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_PROVIDER_NAME);

        return $value === null ? null : (string) $value;
    }

    public function setProviderName(?string $value): static
    {
        return $this->setAttribute(self::COLUMN_PROVIDER_NAME, $value);
    }

    public function getProviderId(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_PROVIDER_ID);

        return $value === null ? null : (int) $value;
    }

    public function setProviderId(?int $value): static
    {
        return $this->setAttribute(self::COLUMN_PROVIDER_ID, $value);
    }

    public function getUnitCost(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_UNIT_COST);

        return $value === null ? null : (int) $value;
    }

    public function setUnitCost(?int $value): static
    {
        return $this->setAttribute(self::COLUMN_UNIT_COST, $value);
    }

    public function getCommissionPercent(): ?float
    {
        $value = $this->getAttribute(self::COLUMN_COMMISSION_PERCENT);

        return $value === null ? null : (float) $value;
    }

    public function setCommissionPercent(?float $value): static
    {
        return $this->setAttribute(self::COLUMN_COMMISSION_PERCENT, $value);
    }

    public function getCommissionSource(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_COMMISSION_SOURCE);

        return $value === null ? null : (string) $value;
    }

    public function setCommissionSource(?string $value): static
    {
        return $this->setAttribute(self::COLUMN_COMMISSION_SOURCE, $value);
    }

    public function getProviderChoice(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_PROVIDER_CHOICE);

        return $value === null ? null : (string) $value;
    }

    public function setProviderChoice(?string $value): static
    {
        return $this->setAttribute(self::COLUMN_PROVIDER_CHOICE, $value);
    }

    public function getOfficialPrice(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_OFFICIAL_PRICE);

        return $value === null ? null : (int) $value;
    }

    public function setOfficialPrice(?int $value): static
    {
        return $this->setAttribute(self::COLUMN_OFFICIAL_PRICE, $value);
    }

    public function getListPrice(): int
    {
        return (int) $this->getAttribute(self::COLUMN_LIST_PRICE);
    }

    public function setListPrice(int $value): static
    {
        return $this->setAttribute(self::COLUMN_LIST_PRICE, $value);
    }

    public function getSalePrice(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_SALE_PRICE);

        return $value === null ? null : (int) $value;
    }

    public function setSalePrice(?int $value): static
    {
        return $this->setAttribute(self::COLUMN_SALE_PRICE, $value);
    }

    public function getUnitPrice(): int
    {
        return (int) $this->getAttribute(self::COLUMN_UNIT_PRICE);
    }

    public function setUnitPrice(int $value): static
    {
        return $this->setAttribute(self::COLUMN_UNIT_PRICE, $value);
    }

    public function getHarajDiscountUnit(): int
    {
        return (int) $this->getAttribute(self::COLUMN_HARAJ_DISCOUNT_UNIT);
    }

    public function setHarajDiscountUnit(int $value): static
    {
        return $this->setAttribute(self::COLUMN_HARAJ_DISCOUNT_UNIT, $value);
    }

    public function getHarajDiscountTotal(): int
    {
        return (int) $this->getAttribute(self::COLUMN_HARAJ_DISCOUNT_TOTAL);
    }

    public function setHarajDiscountTotal(int $value): static
    {
        return $this->setAttribute(self::COLUMN_HARAJ_DISCOUNT_TOTAL, $value);
    }

    public function getCouponDiscountUnit(): int
    {
        return (int) $this->getAttribute(self::COLUMN_COUPON_DISCOUNT_UNIT);
    }

    public function setCouponDiscountUnit(int $value): static
    {
        return $this->setAttribute(self::COLUMN_COUPON_DISCOUNT_UNIT, $value);
    }

    public function getCouponDiscountTotal(): int
    {
        return (int) $this->getAttribute(self::COLUMN_COUPON_DISCOUNT_TOTAL);
    }

    public function setCouponDiscountTotal(int $value): static
    {
        return $this->setAttribute(self::COLUMN_COUPON_DISCOUNT_TOTAL, $value);
    }

    public function getPaidUnit(): int
    {
        return (int) $this->getAttribute(self::COLUMN_PAID_UNIT);
    }

    public function setPaidUnit(int $value): static
    {
        return $this->setAttribute(self::COLUMN_PAID_UNIT, $value);
    }

    public function getPaidTotal(): int
    {
        return (int) $this->getAttribute(self::COLUMN_PAID_TOTAL);
    }

    public function setPaidTotal(int $value): static
    {
        return $this->setAttribute(self::COLUMN_PAID_TOTAL, $value);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function getOrder(): Order
    {
        /** @var Order */
        return $this->order;
    }

    /**
     * The purchase document rows that provided it (S25).
     *
     * @return HasMany<OrderPurchaseItem, $this>
     */
    public function purchaseItems(): HasMany
    {
        return $this->hasMany(OrderPurchaseItem::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_REQUIRES_PRESCRIPTION => 'boolean',
            self::COLUMN_PRODUCT_SNAPSHOT => 'array',
            self::COLUMN_QUANTITY => 'integer',
            self::COLUMN_COMMISSION_PERCENT => 'decimal:2',
            self::COLUMN_UNIT_PRICE => 'integer',
            self::COLUMN_HARAJ_DISCOUNT_UNIT => 'integer',
            self::COLUMN_HARAJ_DISCOUNT_TOTAL => 'integer',
            self::COLUMN_COUPON_DISCOUNT_UNIT => 'integer',
            self::COLUMN_COUPON_DISCOUNT_TOTAL => 'integer',
            self::COLUMN_PAID_UNIT => 'integer',
            self::COLUMN_PAID_TOTAL => 'integer',
        ];
    }
}
