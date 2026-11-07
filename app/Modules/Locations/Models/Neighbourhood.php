<?php

declare(strict_types=1);

namespace App\Modules\Locations\Models;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A neighbourhood (محله) with an optional boundary polygon. Filled in S24 for own-courier zones.
 */
#[Fillable([self::COLUMN_REGION_ID, self::COLUMN_CITY_ID, self::COLUMN_NAME_FA])]
final class Neighbourhood extends Model
{
    use LogsActivity;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_REGION_ID = 'region_id';

    public const string COLUMN_CITY_ID = 'city_id';

    public const string COLUMN_NAME_FA = 'name_fa';

    public const string COLUMN_POLYGON = 'polygon';

    public const string COLUMN_CREATED_AT = 'created_at';

    public const string COLUMN_UPDATED_AT = 'updated_at';

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getRegionId(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_REGION_ID);

        return $value === null ? null : (int) $value;
    }

    public function setRegionId(?int $regionId): static
    {
        return $this->setAttribute(self::COLUMN_REGION_ID, $regionId);
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

    /**
     * The boundary as stored by MySQL (spatial value); written and read with spatial SQL in S24.
     */
    public function getPolygon(): mixed
    {
        return $this->getAttribute(self::COLUMN_POLYGON);
    }

    /**
     * Sets the boundary from a spatial expression, e.g. DB::raw("ST_GeomFromText('POLYGON(...)', 4326)").
     */
    public function setPolygon(?Expression $polygon): static
    {
        return $this->setAttribute(self::COLUMN_POLYGON, $polygon);
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
     * @return BelongsTo<Region, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, self::COLUMN_REGION_ID);
    }

    public function getRegion(): ?Region
    {
        return $this->getRelationValue('region');
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('locations')->logOnly([self::COLUMN_NAME_FA, self::COLUMN_REGION_ID])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
