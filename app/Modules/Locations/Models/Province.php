<?php

declare(strict_types=1);

namespace App\Modules\Locations\Models;

use App\Modules\Locations\Database\Factories\ProvinceFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A province (Tipax «state»): the id is Tipax's stateId. Created by `locations:import-tipax` from Tipax's city list
 * (roadmap D4, decided 2026-11-14 in S18).
 */
final class Province extends Model
{
    /** @use HasFactory<ProvinceFactory> */
    use HasFactory;

    use LogsActivity;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_NAME_FA = 'name_fa';

    public const string COLUMN_CREATED_AT = 'created_at';

    public const string COLUMN_UPDATED_AT = 'updated_at';

    /**
     * Ids are Tipax's stateId, set on import.
     */
    public $incrementing = false;

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

    public function getCreatedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_CREATED_AT);
    }

    public function getUpdatedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_UPDATED_AT);
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
