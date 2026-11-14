<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Database\Factories\AddressFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A customer's delivery address (roadmap D3.3, S18): no map pin, only the postal code and the full address. Only the
 * owner sees it (AddressPolicy). Customers write these rows themselves, so they are not in the admin audit log.
 */
final class Address extends Model
{
    /** @use HasFactory<AddressFactory> */
    use HasFactory;

    use SoftDeletes;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_CUSTOMER_ID = 'customer_id';

    public const string COLUMN_TITLE = 'title';

    public const string COLUMN_RECEIVER_NAME = 'receiver_name';

    public const string COLUMN_RECEIVER_MOBILE = 'receiver_mobile';

    public const string COLUMN_PROVINCE_ID = 'province_id';

    public const string COLUMN_CITY_ID = 'city_id';

    public const string COLUMN_FULL_ADDRESS = 'full_address';

    public const string COLUMN_PLAQUE = 'plaque';

    public const string COLUMN_UNIT = 'unit';

    public const string COLUMN_FLOOR = 'floor';

    public const string COLUMN_POSTAL_CODE = 'postal_code';

    public const string COLUMN_IS_DEFAULT = 'is_default';

    public const string COLUMN_CREATED_AT = 'created_at';

    /** Addresses one customer may keep. */
    public const int MAX_PER_CUSTOMER = 10;

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

    public function getTitle(): string
    {
        return (string) $this->getAttribute(self::COLUMN_TITLE);
    }

    public function setTitle(string $title): static
    {
        return $this->setAttribute(self::COLUMN_TITLE, $title);
    }

    public function getReceiverName(): string
    {
        return (string) $this->getAttribute(self::COLUMN_RECEIVER_NAME);
    }

    public function setReceiverName(string $receiverName): static
    {
        return $this->setAttribute(self::COLUMN_RECEIVER_NAME, $receiverName);
    }

    public function getReceiverMobile(): string
    {
        return (string) $this->getAttribute(self::COLUMN_RECEIVER_MOBILE);
    }

    public function setReceiverMobile(string $receiverMobile): static
    {
        return $this->setAttribute(self::COLUMN_RECEIVER_MOBILE, $receiverMobile);
    }

    public function getProvinceId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_PROVINCE_ID);
    }

    public function setProvinceId(int $provinceId): static
    {
        return $this->setAttribute(self::COLUMN_PROVINCE_ID, $provinceId);
    }

    public function getCityId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_CITY_ID);
    }

    public function setCityId(int $cityId): static
    {
        return $this->setAttribute(self::COLUMN_CITY_ID, $cityId);
    }

    public function getFullAddress(): string
    {
        return (string) $this->getAttribute(self::COLUMN_FULL_ADDRESS);
    }

    public function setFullAddress(string $fullAddress): static
    {
        return $this->setAttribute(self::COLUMN_FULL_ADDRESS, $fullAddress);
    }

    public function getPlaque(): string
    {
        return (string) $this->getAttribute(self::COLUMN_PLAQUE);
    }

    public function setPlaque(string $plaque): static
    {
        return $this->setAttribute(self::COLUMN_PLAQUE, $plaque);
    }

    public function getUnit(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_UNIT);

        return $value === null ? null : (string) $value;
    }

    public function setUnit(?string $unit): static
    {
        return $this->setAttribute(self::COLUMN_UNIT, $unit);
    }

    public function getFloor(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_FLOOR);

        return $value === null ? null : (string) $value;
    }

    public function setFloor(?string $floor): static
    {
        return $this->setAttribute(self::COLUMN_FLOOR, $floor);
    }

    public function getPostalCode(): string
    {
        return (string) $this->getAttribute(self::COLUMN_POSTAL_CODE);
    }

    public function setPostalCode(string $postalCode): static
    {
        return $this->setAttribute(self::COLUMN_POSTAL_CODE, $postalCode);
    }

    public function isDefault(): bool
    {
        return (bool) $this->getAttribute(self::COLUMN_IS_DEFAULT);
    }

    public function setIsDefault(bool $isDefault): static
    {
        return $this->setAttribute(self::COLUMN_IS_DEFAULT, $isDefault);
    }

    public function getCreatedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_CREATED_AT);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, self::COLUMN_CUSTOMER_ID);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOwnedBy(Builder $query, int $customerId): Builder
    {
        return $query->where(self::COLUMN_CUSTOMER_ID, $customerId);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_IS_DEFAULT => 'boolean',
        ];
    }
}
