<?php

declare(strict_types=1);

namespace App\Modules\Locations\Models;

use App\Modules\Locations\Database\Factories\CityFactory;
use App\Modules\Locations\Enums\CityType;
use App\Modules\Shared\Contracts\HasActivityLogLabel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A city. Coordinates are the county centre from GeoNames when known; delivery is switched on by admins (roadmap D4).
 */
#[Fillable([
    self::COLUMN_PROVINCE_ID, self::COLUMN_COUNTY_ID, self::COLUMN_NAME_FA, self::COLUMN_SLUG, self::COLUMN_TYPE,
    self::COLUMN_LATITUDE, self::COLUMN_LONGITUDE, self::COLUMN_IS_DELIVERY_ENABLED, self::COLUMN_SOURCE_ID,
])]
final class City extends Model implements HasActivityLogLabel
{
    /** @use HasFactory<CityFactory> */
    use HasFactory;

    use LogsActivity;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_PROVINCE_ID = 'province_id';

    public const string COLUMN_COUNTY_ID = 'county_id';

    public const string COLUMN_NAME_FA = 'name_fa';

    public const string COLUMN_SLUG = 'slug';

    public const string COLUMN_TYPE = 'type';

    public const string COLUMN_LATITUDE = 'latitude';

    public const string COLUMN_LONGITUDE = 'longitude';

    public const string COLUMN_IS_DELIVERY_ENABLED = 'is_delivery_enabled';

    public const string COLUMN_SOURCE_ID = 'source_id';

    public const string COLUMN_CREATED_AT = 'created_at';

    public const string COLUMN_UPDATED_AT = 'updated_at';

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getProvinceId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_PROVINCE_ID);
    }

    public function setProvinceId(int $provinceId): static
    {
        return $this->setAttribute(self::COLUMN_PROVINCE_ID, $provinceId);
    }

    public function getCountyId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_COUNTY_ID);
    }

    public function setCountyId(int $countyId): static
    {
        return $this->setAttribute(self::COLUMN_COUNTY_ID, $countyId);
    }

    public function getNameFa(): string
    {
        return (string) $this->getAttribute(self::COLUMN_NAME_FA);
    }

    public function setNameFa(string $nameFa): static
    {
        return $this->setAttribute(self::COLUMN_NAME_FA, $nameFa);
    }

    public function getSlug(): string
    {
        return (string) $this->getAttribute(self::COLUMN_SLUG);
    }

    public function setSlug(string $slug): static
    {
        return $this->setAttribute(self::COLUMN_SLUG, $slug);
    }

    public function getType(): CityType
    {
        return $this->getAttribute(self::COLUMN_TYPE);
    }

    public function setType(CityType $type): static
    {
        return $this->setAttribute(self::COLUMN_TYPE, $type);
    }

    public function getLatitude(): ?float
    {
        return $this->getAttribute(self::COLUMN_LATITUDE);
    }

    public function setLatitude(?float $latitude): static
    {
        return $this->setAttribute(self::COLUMN_LATITUDE, $latitude);
    }

    public function getLongitude(): ?float
    {
        return $this->getAttribute(self::COLUMN_LONGITUDE);
    }

    public function setLongitude(?float $longitude): static
    {
        return $this->setAttribute(self::COLUMN_LONGITUDE, $longitude);
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

    public function getSourceId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_SOURCE_ID);
    }

    public function setSourceId(int $sourceId): static
    {
        return $this->setAttribute(self::COLUMN_SOURCE_ID, $sourceId);
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
     * @return BelongsTo<County, $this>
     */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class, self::COLUMN_COUNTY_ID);
    }

    public function getCounty(): County
    {
        return $this->getRelationValue('county');
    }

    /**
     * @return HasMany<Region, $this>
     */
    public function regions(): HasMany
    {
        return $this->hasMany(Region::class, Region::COLUMN_CITY_ID);
    }

    /**
     * @return Collection<int, Region>
     */
    public function getRegions(): Collection
    {
        return $this->getRelationValue('regions');
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
            self::COLUMN_TYPE => CityType::class,
            self::COLUMN_LATITUDE => 'float',
            self::COLUMN_LONGITUDE => 'float',
            self::COLUMN_IS_DELIVERY_ENABLED => 'boolean',
        ];
    }
}
