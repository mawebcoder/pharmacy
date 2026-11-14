<?php

declare(strict_types=1);

namespace App\Modules\Locations\Models;

use App\Modules\Locations\Database\Factories\CityFactory;
use App\Modules\Shared\Contracts\HasActivityLogLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A Tipax city (roadmap D4, decided 2026-11-14 in S18): the id is Tipax's city id, so addresses and shipments use it
 * as is. Imported by `locations:import-tipax`; admins switch delivery on per city («ارسال فعال», S05).
 */
final class City extends Model implements HasActivityLogLabel
{
    /** @use HasFactory<CityFactory> */
    use HasFactory;

    use LogsActivity;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_PROVINCE_ID = 'province_id';

    public const string COLUMN_NAME_FA = 'name_fa';

    public const string COLUMN_LATITUDE = 'latitude';

    public const string COLUMN_LONGITUDE = 'longitude';

    public const string COLUMN_HAS_TIPAX_BRANCH = 'has_tipax_branch';

    public const string COLUMN_IS_DELIVERY_ENABLED = 'is_delivery_enabled';

    public const string COLUMN_TIPAX_SYNCED_AT = 'tipax_synced_at';

    public const string COLUMN_CREATED_AT = 'created_at';

    public const string COLUMN_UPDATED_AT = 'updated_at';

    /**
     * Ids are Tipax's, set on import.
     */
    public $incrementing = false;

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    /**
     * Tipax's city id (the same as the id).
     */
    public function setId(int $tipaxCityId): static
    {
        return $this->setAttribute(self::COLUMN_ID, $tipaxCityId);
    }

    public function getProvinceId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_PROVINCE_ID);
    }

    public function setProvinceId(int $provinceId): static
    {
        return $this->setAttribute(self::COLUMN_PROVINCE_ID, $provinceId);
    }

    public function getNameFa(): string
    {
        return (string) $this->getAttribute(self::COLUMN_NAME_FA);
    }

    public function setNameFa(string $nameFa): static
    {
        return $this->setAttribute(self::COLUMN_NAME_FA, $nameFa);
    }

    public function getLatitude(): ?float
    {
        $value = $this->getAttribute(self::COLUMN_LATITUDE);

        return $value === null ? null : (float) $value;
    }

    public function getLongitude(): ?float
    {
        $value = $this->getAttribute(self::COLUMN_LONGITUDE);

        return $value === null ? null : (float) $value;
    }

    public function hasTipaxBranch(): bool
    {
        return (bool) $this->getAttribute(self::COLUMN_HAS_TIPAX_BRANCH);
    }

    public function getHasTipaxBranch(): bool
    {
        return $this->hasTipaxBranch();
    }

    public function setHasTipaxBranch(bool $hasBranch): static
    {
        return $this->setAttribute(self::COLUMN_HAS_TIPAX_BRANCH, $hasBranch);
    }

    public function isDeliveryEnabled(): bool
    {
        return (bool) $this->getAttribute(self::COLUMN_IS_DELIVERY_ENABLED);
    }

    public function getIsDeliveryEnabled(): bool
    {
        return $this->isDeliveryEnabled();
    }

    public function setIsDeliveryEnabled(bool $isDeliveryEnabled): static
    {
        return $this->setAttribute(self::COLUMN_IS_DELIVERY_ENABLED, $isDeliveryEnabled);
    }

    public function getTipaxSyncedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_TIPAX_SYNCED_AT);
    }

    public function setTipaxSyncedAt(?Carbon $syncedAt): static
    {
        return $this->setAttribute(self::COLUMN_TIPAX_SYNCED_AT, $syncedAt);
    }

    public function getCreatedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_CREATED_AT);
    }

    public function getUpdatedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_UPDATED_AT);
    }

    /**
     * @return BelongsTo<Province, $this>
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class, self::COLUMN_PROVINCE_ID);
    }

    public function getProvince(): Province
    {
        return $this->getRelationValue('province');
    }

    /**
     * @param  Builder<City>  $query
     * @return Builder<City>
     */
    public function scopeDeliveryEnabled(Builder $query): Builder
    {
        return $query->where(self::COLUMN_IS_DELIVERY_ENABLED, true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('locations')->logOnly([self::COLUMN_IS_DELIVERY_ENABLED])->logOnlyDirty()->dontLogEmptyChanges();
    }

    public function activityLogLabel(): string
    {
        return 'شهر: '.$this->getNameFa();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_ID => 'integer',
            self::COLUMN_PROVINCE_ID => 'integer',
            self::COLUMN_HAS_TIPAX_BRANCH => 'boolean',
            self::COLUMN_IS_DELIVERY_ENABLED => 'boolean',
            self::COLUMN_TIPAX_SYNCED_AT => 'datetime',
        ];
    }
}
