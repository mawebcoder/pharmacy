<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Models;

use App\Modules\Shared\Contracts\HasActivityLogLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A supplier's commission for one brand (roadmap D3.2, S15b, level 4: over the brand's own, under any product
 * commission). brand_id is a Catalog brands id (read as a foreign key only).
 */
final class BrandProvider extends Model implements HasActivityLogLabel
{
    use LogsActivity;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_BRAND_ID = 'brand_id';

    public const string COLUMN_PROVIDER_ID = 'provider_id';

    public const string COLUMN_COMMISSION_PERCENT = 'commission_percent';

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getBrandId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_BRAND_ID);
    }

    public function setBrandId(int $brandId): static
    {
        return $this->setAttribute(self::COLUMN_BRAND_ID, $brandId);
    }

    public function getProviderId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_PROVIDER_ID);
    }

    public function setProviderId(int $providerId): static
    {
        return $this->setAttribute(self::COLUMN_PROVIDER_ID, $providerId);
    }

    public function getCommissionPercent(): float
    {
        return (float) $this->getAttribute(self::COLUMN_COMMISSION_PERCENT);
    }

    public function setCommissionPercent(float $commissionPercent): static
    {
        return $this->setAttribute(self::COLUMN_COMMISSION_PERCENT, $commissionPercent);
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
        return 'کمیسیون تأمین‌کننده برای برند #'.$this->getBrandId().': '.($this->getProvider()?->getName() ?? '');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('pricing')
            ->logOnly([self::COLUMN_BRAND_ID, self::COLUMN_PROVIDER_ID, self::COLUMN_COMMISSION_PERCENT])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_BRAND_ID => 'integer',
            self::COLUMN_PROVIDER_ID => 'integer',
            self::COLUMN_COMMISSION_PERCENT => 'decimal:2',
        ];
    }
}
