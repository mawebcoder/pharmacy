<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Models;

use App\Modules\Shared\Contracts\HasActivityLogLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A supplier's settings for one product (roadmap D3.2, S15b): its commission for this product (level 2, over the
 * product's own) and whether it is the product's preferred supplier (for every SKU, unless a SKU has its own).
 * product_id is a Catalog products id (read as a foreign key only).
 */
final class ProductProvider extends Model implements HasActivityLogLabel
{
    use LogsActivity;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_PRODUCT_ID = 'product_id';

    public const string COLUMN_PROVIDER_ID = 'provider_id';

    public const string COLUMN_COMMISSION_PERCENT = 'commission_percent';

    public const string COLUMN_IS_PREFERRED = 'is_preferred';

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getProductId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_PRODUCT_ID);
    }

    public function setProductId(int $productId): static
    {
        return $this->setAttribute(self::COLUMN_PRODUCT_ID, $productId);
    }

    public function getProviderId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_PROVIDER_ID);
    }

    public function setProviderId(int $providerId): static
    {
        return $this->setAttribute(self::COLUMN_PROVIDER_ID, $providerId);
    }

    public function getCommissionPercent(): ?float
    {
        $value = $this->getAttribute(self::COLUMN_COMMISSION_PERCENT);

        return $value === null ? null : (float) $value;
    }

    public function setCommissionPercent(?float $commissionPercent): static
    {
        return $this->setAttribute(self::COLUMN_COMMISSION_PERCENT, $commissionPercent);
    }

    public function isPreferred(): bool
    {
        return (bool) $this->getAttribute(self::COLUMN_IS_PREFERRED);
    }

    public function getIsPreferred(): bool
    {
        return $this->isPreferred();
    }

    public function setIsPreferred(bool $isPreferred): static
    {
        return $this->setAttribute(self::COLUMN_IS_PREFERRED, $isPreferred);
    }

    /**
     * @return BelongsTo<Provider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function getProvider(): ?Provider
    {
        return $this->provider;
    }

    public function activityLogLabel(): string
    {
        return 'تأمین‌کننده محصول #'.$this->getProductId().': '.($this->getProvider()?->getName() ?? '');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('pricing')
            ->logOnly([self::COLUMN_PRODUCT_ID, self::COLUMN_PROVIDER_ID, self::COLUMN_COMMISSION_PERCENT, self::COLUMN_IS_PREFERRED])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_PRODUCT_ID => 'integer',
            self::COLUMN_PROVIDER_ID => 'integer',
            self::COLUMN_COMMISSION_PERCENT => 'decimal:2',
            self::COLUMN_IS_PREFERRED => 'boolean',
        ];
    }
}
