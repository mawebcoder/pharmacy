<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A product a customer saved with the heart button (roadmap D3.3, S18). The customer is referred to by id only
 * (Identity owns customers). Customers write these rows themselves, so they are not in the admin audit log.
 */
final class Wishlist extends Model
{
    public const string COLUMN_ID = 'id';

    public const string COLUMN_CUSTOMER_ID = 'customer_id';

    public const string COLUMN_PRODUCT_ID = 'product_id';

    public const string COLUMN_CREATED_AT = 'created_at';

    public const UPDATED_AT = null;

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getCustomerId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_CUSTOMER_ID);
    }

    public function setCustomerId(int $customerId): static
    {
        return $this->setAttribute(self::COLUMN_CUSTOMER_ID, $customerId);
    }

    public function getProductId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_PRODUCT_ID);
    }

    public function setProductId(int $productId): static
    {
        return $this->setAttribute(self::COLUMN_PRODUCT_ID, $productId);
    }

    public function getCreatedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_CREATED_AT);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, self::COLUMN_PRODUCT_ID);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_CREATED_AT => 'datetime',
        ];
    }
}
