<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\AttributeFactory;
use App\Modules\Catalog\Enums\AttributeType;
use App\Modules\Shared\Contracts\HasActivityLogLabel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A product detail chosen from a list, used for filters (roadmap D3.1), e.g. «شکل محصول»: قرص، کرم، شربت…
 */
final class Attribute extends Model implements HasActivityLogLabel
{
    /** @use HasFactory<AttributeFactory> */
    use HasFactory;

    use LogsActivity;
    use SoftDeletes;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_NAME_FA = 'name_fa';

    public const string COLUMN_CODE = 'code';

    public const string COLUMN_TYPE = 'type';

    public const string COLUMN_IS_FILTERABLE = 'is_filterable';

    public const string COLUMN_SORT = 'sort';

    public const string COLUMN_DELETED_AT = 'deleted_at';

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

    /**
     * Stable machine name used in filter URLs and code, e.g. "form" (?form[]=…).
     */
    public function getCode(): string
    {
        return (string) $this->getAttribute(self::COLUMN_CODE);
    }

    public function setCode(string $code): static
    {
        return $this->setAttribute(self::COLUMN_CODE, $code);
    }

    public function getType(): AttributeType
    {
        return $this->getAttribute(self::COLUMN_TYPE);
    }

    public function setType(AttributeType $type): static
    {
        return $this->setAttribute(self::COLUMN_TYPE, $type);
    }

    public function isMultiple(): bool
    {
        return $this->getType() === AttributeType::MultiSelect;
    }

    public function isFilterable(): bool
    {
        return (bool) $this->getAttribute(self::COLUMN_IS_FILTERABLE);
    }

    public function getIsFilterable(): bool
    {
        return $this->isFilterable();
    }

    public function setIsFilterable(bool $isFilterable): static
    {
        return $this->setAttribute(self::COLUMN_IS_FILTERABLE, $isFilterable);
    }

    public function getSort(): int
    {
        return (int) $this->getAttribute(self::COLUMN_SORT);
    }

    public function setSort(int $sort): static
    {
        return $this->setAttribute(self::COLUMN_SORT, $sort);
    }

    /**
     * @return HasMany<AttributeValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class)->orderBy(AttributeValue::COLUMN_SORT)->orderBy(AttributeValue::COLUMN_ID);
    }

    /**
     * @return Collection<int, AttributeValue>
     */
    public function getValues(): Collection
    {
        return $this->getRelationValue('values');
    }

    /**
     * Whether any (not deleted) product uses one of its values.
     */
    public function isInUse(): bool
    {
        return AttributeValue::query()
            ->where(AttributeValue::COLUMN_ATTRIBUTE_ID, $this->getId())
            ->whereHas('products')
            ->exists();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('catalog')
            ->logOnly([self::COLUMN_NAME_FA, self::COLUMN_CODE, self::COLUMN_TYPE, self::COLUMN_IS_FILTERABLE, self::COLUMN_SORT])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function activityLogLabel(): string
    {
        return 'ویژگی: '.$this->getNameFa();
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_TYPE => AttributeType::class,
            self::COLUMN_IS_FILTERABLE => 'boolean',
            self::COLUMN_SORT => 'integer',
        ];
    }
}
