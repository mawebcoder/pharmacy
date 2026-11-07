<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\AttributeValueFactory;
use App\Modules\Shared\Contracts\HasActivityLogLabel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One choice of an attribute, e.g. «کرم» of «شکل محصول». A value used by products cannot be deleted.
 */
final class AttributeValue extends Model implements HasActivityLogLabel
{
    /** @use HasFactory<AttributeValueFactory> */
    use HasFactory;

    use LogsActivity;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_ATTRIBUTE_ID = 'attribute_id';

    public const string COLUMN_VALUE_FA = 'value_fa';

    public const string COLUMN_SORT = 'sort';

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getAttributeId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ATTRIBUTE_ID);
    }

    public function setAttributeId(int $attributeId): static
    {
        return $this->setAttribute(self::COLUMN_ATTRIBUTE_ID, $attributeId);
    }

    public function getValueFa(): string
    {
        return (string) $this->getAttribute(self::COLUMN_VALUE_FA);
    }

    public function setValueFa(string $valueFa): static
    {
        return $this->setAttribute(self::COLUMN_VALUE_FA, $valueFa);
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
     * @return BelongsTo<Attribute, $this>
     */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class)->withTrashed();
    }

    public function getAttributeModel(): ?Attribute
    {
        return $this->getRelationValue('attribute');
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_attribute_value');
    }

    public function isInUse(): bool
    {
        return $this->products()->exists();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('catalog')
            ->logOnly([self::COLUMN_ATTRIBUTE_ID, self::COLUMN_VALUE_FA, self::COLUMN_SORT])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function activityLogLabel(): string
    {
        return 'مقدار ویژگی: '.$this->getValueFa();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_ATTRIBUTE_ID => 'integer',
            self::COLUMN_SORT => 'integer',
        ];
    }
}
