<?php

declare(strict_types=1);

namespace App\Modules\Locations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A city region (منطقه شهرداری), e.g. one of Tehran's 22 districts. Filled in S24.
 */
#[Fillable([self::COLUMN_CITY_ID, self::COLUMN_NAME_FA, self::COLUMN_NUMBER])]
final class Region extends Model
{
    use LogsActivity;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_CITY_ID = 'city_id';

    public const string COLUMN_NAME_FA = 'name_fa';

    public const string COLUMN_NUMBER = 'number';

    public const string COLUMN_CREATED_AT = 'created_at';

    public const string COLUMN_UPDATED_AT = 'updated_at';

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getCityId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_CITY_ID);
    }

    public function setCityId(int $cityId): static
    {
        return $this->setAttribute(self::COLUMN_CITY_ID, $cityId);
    }

    public function getNameFa(): string
    {
        return (string) $this->getAttribute(self::COLUMN_NAME_FA);
    }

    public function setNameFa(string $nameFa): static
    {
        return $this->setAttribute(self::COLUMN_NAME_FA, $nameFa);
    }

    public function getNumber(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_NUMBER);

        return $value === null ? null : (int) $value;
    }

    public function setNumber(?int $number): static
    {
        return $this->setAttribute(self::COLUMN_NUMBER, $number);
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
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, self::COLUMN_CITY_ID);
    }

    public function getCity(): City
    {
        return $this->getRelationValue('city');
    }

    /**
     * @return HasMany<Neighbourhood, $this>
     */
    public function neighbourhoods(): HasMany
    {
        return $this->hasMany(Neighbourhood::class, Neighbourhood::COLUMN_REGION_ID);
    }

    /**
     * @return Collection<int, Neighbourhood>
     */
    public function getNeighbourhoods(): Collection
    {
        return $this->getRelationValue('neighbourhoods');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('locations')->logOnly([self::COLUMN_NAME_FA, self::COLUMN_NUMBER])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
