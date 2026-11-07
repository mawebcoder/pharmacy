<?php

declare(strict_types=1);

namespace App\Modules\Locations\Models;

use App\Modules\Locations\Database\Factories\ProvinceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * An Iranian province (استان), imported from the location dataset (roadmap D4).
 */
#[Fillable([self::COLUMN_NAME_FA, self::COLUMN_SLUG, self::COLUMN_TEL_PREFIX, self::COLUMN_SOURCE_ID])]
final class Province extends Model
{
    /** @use HasFactory<ProvinceFactory> */
    use HasFactory;

    use LogsActivity;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_NAME_FA = 'name_fa';

    public const string COLUMN_SLUG = 'slug';

    public const string COLUMN_TEL_PREFIX = 'tel_prefix';

    public const string COLUMN_SOURCE_ID = 'source_id';

    public const string COLUMN_CREATED_AT = 'created_at';

    public const string COLUMN_UPDATED_AT = 'updated_at';

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
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

    public function getTelPrefix(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_TEL_PREFIX);

        return $value === null ? null : (string) $value;
    }

    public function setTelPrefix(?string $telPrefix): static
    {
        return $this->setAttribute(self::COLUMN_TEL_PREFIX, $telPrefix);
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
     * @return HasMany<County, $this>
     */
    public function counties(): HasMany
    {
        return $this->hasMany(County::class, County::COLUMN_PROVINCE_ID);
    }

    /**
     * @return Collection<int, County>
     */
    public function getCounties(): Collection
    {
        return $this->getRelationValue('counties');
    }

    /**
     * @return HasMany<City, $this>
     */
    public function cities(): HasMany
    {
        return $this->hasMany(City::class, City::COLUMN_PROVINCE_ID);
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
