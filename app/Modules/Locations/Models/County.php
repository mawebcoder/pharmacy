<?php

declare(strict_types=1);

namespace App\Modules\Locations\Models;

use App\Modules\Locations\Database\Factories\CountyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A county (شهرستان) of a province (roadmap D4).
 */
#[Fillable([self::COLUMN_PROVINCE_ID, self::COLUMN_NAME_FA, self::COLUMN_SLUG, self::COLUMN_SOURCE_ID])]
final class County extends Model
{
    /** @use HasFactory<CountyFactory> */
    use HasFactory;

    use LogsActivity;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_PROVINCE_ID = 'province_id';

    public const string COLUMN_NAME_FA = 'name_fa';

    public const string COLUMN_SLUG = 'slug';

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
     * @return HasMany<City, $this>
     */
    public function cities(): HasMany
    {
        return $this->hasMany(City::class, City::COLUMN_COUNTY_ID);
    }

    /**
     * @return Collection<int, City>
     */
    public function getCities(): Collection
    {
        return $this->getRelationValue('cities');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('locations')->logOnly([self::COLUMN_NAME_FA])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
