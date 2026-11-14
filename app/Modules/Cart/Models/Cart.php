<?php

declare(strict_types=1);

namespace App\Modules\Cart\Models;

use App\Modules\Cart\Database\Factories\CartFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A shopping cart (roadmap D3.4, S19): a customer's (customer_id, kept until checkout) or a guest's (the hash of the
 * cookie token, kept 30 days). Not audited: carts are the shopper's own working rows.
 */
final class Cart extends Model
{
    /** @use HasFactory<CartFactory> */
    use HasFactory;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_CUSTOMER_ID = 'customer_id';

    public const string COLUMN_SESSION_TOKEN_HASH = 'session_token_hash';

    public const string COLUMN_EXPIRES_AT = 'expires_at';

    public const string COLUMN_UPDATED_AT = 'updated_at';

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getCustomerId(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_CUSTOMER_ID);

        return $value === null ? null : (int) $value;
    }

    public function setCustomerId(?int $customerId): static
    {
        return $this->setAttribute(self::COLUMN_CUSTOMER_ID, $customerId);
    }

    public function getSessionTokenHash(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_SESSION_TOKEN_HASH);

        return $value === null ? null : (string) $value;
    }

    public function setSessionTokenHash(?string $hash): static
    {
        return $this->setAttribute(self::COLUMN_SESSION_TOKEN_HASH, $hash);
    }

    public function getExpiresAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_EXPIRES_AT);
    }

    public function setExpiresAt(?Carbon $expiresAt): static
    {
        return $this->setAttribute(self::COLUMN_EXPIRES_AT, $expiresAt);
    }

    /**
     * @return HasMany<CartItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class)->orderBy(CartItem::COLUMN_ID);
    }

    /**
     * @return Collection<int, CartItem>
     */
    public function getItems(): Collection
    {
        return $this->getRelationValue('items');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_CUSTOMER_ID => 'integer',
            self::COLUMN_EXPIRES_AT => 'datetime',
        ];
    }
}
