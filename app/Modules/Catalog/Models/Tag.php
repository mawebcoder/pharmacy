<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\TagFactory;
use App\Modules\Catalog\Enums\TagType;
use App\Modules\Media\Concerns\InteractsWithImagePresets;
use App\Modules\Media\Contracts\HasImagePresets;
use App\Modules\Media\Contracts\StoredImage;
use App\Modules\Shared\Contracts\HasActivityLogLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A nutrient or need shortcut, e.g. «آهن» or «بی‌خوابی» (roadmap D3.1, B1 Darochi). Shown as chips; featured tags
 * appear on the home page. /t/{slug} lists its products.
 */
final class Tag extends Model implements HasActivityLogLabel, HasImagePresets
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    use InteractsWithImagePresets;
    use LogsActivity;
    use SoftDeletes;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_NAME_FA = 'name_fa';

    public const string COLUMN_NAME_EN = 'name_en';

    public const string COLUMN_SLUG = 'slug';

    public const string COLUMN_TYPE = 'type';

    public const string COLUMN_IS_FEATURED = 'is_featured';

    public const string COLUMN_SORT = 'sort';

    public const string COLUMN_DELETED_AT = 'deleted_at';

    /**
     * Chip icon (preset category_icon: SVG preferred).
     */
    public const string IMAGE_ICON = 'icon';

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

    public function getNameEn(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_NAME_EN);

        return $value === null ? null : (string) $value;
    }

    public function setNameEn(?string $nameEn): static
    {
        return $this->setAttribute(self::COLUMN_NAME_EN, $nameEn);
    }

    public function getSlug(): string
    {
        return (string) $this->getAttribute(self::COLUMN_SLUG);
    }

    public function setSlug(string $slug): static
    {
        return $this->setAttribute(self::COLUMN_SLUG, $slug);
    }

    public function getType(): TagType
    {
        return $this->getAttribute(self::COLUMN_TYPE);
    }

    public function setType(TagType $type): static
    {
        return $this->setAttribute(self::COLUMN_TYPE, $type);
    }

    public function isFeatured(): bool
    {
        return (bool) $this->getAttribute(self::COLUMN_IS_FEATURED);
    }

    public function getIsFeatured(): bool
    {
        return $this->isFeatured();
    }

    public function setIsFeatured(bool $isFeatured): static
    {
        return $this->setAttribute(self::COLUMN_IS_FEATURED, $isFeatured);
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
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    /**
     * @return array<string, string>
     */
    public function imagePresets(): array
    {
        return [self::IMAGE_ICON => 'category_icon'];
    }

    public function getIcon(): ?StoredImage
    {
        return $this->image(self::IMAGE_ICON);
    }

    /**
     * Featured tags in their chip order.
     *
     * @param  Builder<Tag>  $query
     * @return Builder<Tag>
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where(self::COLUMN_IS_FEATURED, true)->orderBy(self::COLUMN_SORT)->orderBy(self::COLUMN_ID);
    }

    public function getRouteKeyName(): string
    {
        return self::COLUMN_SLUG;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('catalog')
            ->logOnly([self::COLUMN_NAME_FA, self::COLUMN_NAME_EN, self::COLUMN_SLUG, self::COLUMN_TYPE, self::COLUMN_IS_FEATURED, self::COLUMN_SORT])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function activityLogLabel(): string
    {
        return 'برچسب: '.$this->getNameFa();
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_TYPE => TagType::class,
            self::COLUMN_IS_FEATURED => 'boolean',
            self::COLUMN_SORT => 'integer',
        ];
    }
}
