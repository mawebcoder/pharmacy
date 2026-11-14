<?php

declare(strict_types=1);

namespace App\Modules\Cart\Models;

use App\Modules\Cart\Database\Factories\CartItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One SKU in a cart (roadmap D3.4). unit_price_snapshot is the price when it was last seen by the shopper, kept only
 * to tell them when the price has changed; the cart always charges the current price.
 */
final class CartItem extends Model
{
    /** @use HasFactory<CartItemFactory> */
    use HasFactory;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_CART_ID = 'cart_id';

    public const string COLUMN_VARIANT_ID = 'variant_id';

    public const string COLUMN_QUANTITY = 'quantity';

    public const string COLUMN_UNIT_PRICE_SNAPSHOT = 'unit_price_snapshot';

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getCartId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_CART_ID);
    }

    public function setCartId(int $cartId): static
    {
        return $this->setAttribute(self::COLUMN_CART_ID, $cartId);
    }

    public function getVariantId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_VARIANT_ID);
    }

    public function setVariantId(int $variantId): static
    {
        return $this->setAttribute(self::COLUMN_VARIANT_ID, $variantId);
    }

    public function getQuantity(): int
    {
        return (int) $this->getAttribute(self::COLUMN_QUANTITY);
    }

    public function setQuantity(int $quantity): static
    {
        return $this->setAttribute(self::COLUMN_QUANTITY, $quantity);
    }

    public function getUnitPriceSnapshot(): int
    {
        return (int) $this->getAttribute(self::COLUMN_UNIT_PRICE_SNAPSHOT);
    }

    public function setUnitPriceSnapshot(int $price): static
    {
        return $this->setAttribute(self::COLUMN_UNIT_PRICE_SNAPSHOT, $price);
    }

    /**
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_CART_ID => 'integer',
            self::COLUMN_VARIANT_ID => 'integer',
            self::COLUMN_QUANTITY => 'integer',
            self::COLUMN_UNIT_PRICE_SNAPSHOT => 'integer',
        ];
    }
}
