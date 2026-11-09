<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\ProductCollectionFactory;
use App\Modules\Media\Concerns\InteractsWithImagePresets;
use App\Modules\Media\Contracts\HasImagePresets;
use App\Modules\Media\Contracts\StoredImage;
use App\Modules\Shared\Contracts\HasActivityLogLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A hand-picked list of products in the admin's order, e.g. «ضدآفتاب تابستان» (roadmap D3.1, S13b). Listed at
 * /collections/{slug} and usable as a home widget. The system collection «محصولات محبوب» (slug popular) feeds the
 * empty search box and cannot be deleted. Each may have a small icon shown beside its name. Named ProductCollection
 * so it is not confused with Eloquent collections.
 */
final class ProductCollection extends Model implements HasActivityLogLabel, HasImagePresets
{
    /** @use HasFactory<ProductCollectionFactory> */
    use HasFactory;

    use InteractsWithImagePresets;
    use LogsActivity;
    use SoftDeletes;

    public const string POPULAR_SLUG = 'popular';

    public const string COLUMN_ID = 'id';

    public const string COLUMN_NAME_FA = 'name_fa';

    public const string COLUMN_NAME_EN = 'name_en';

    public const string COLUMN_SLUG = 'slug';

    public const string COLUMN_DESCRIPTION = 'description';

    public const string COLUMN_IS_SYSTEM = 'is_system';

    public const string COLUMN_IS_ACTIVE = 'is_active';

    public const string COLUMN_STARTS_AT = 'starts_at';

    public const string COLUMN_ENDS_AT = 'ends_at';

    public const string COLUMN_SORT = 'sort';

    public const string PIVOT_SORT = 'sort';

    /**
     * Small icon beside the name (preset category_icon: SVG preferred).
     */
    public const string IMAGE_ICON = 'icon';

    protected $table = 'collections';

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

    public function getDescription(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_DESCRIPTION);

        return $value === null ? null : (string) $value;
    }

    public function setDescription(?string $description): static
    {
        return $this->setAttribute(self::COLUMN_DESCRIPTION, $description);
    }

    public function isSystem(): bool
    {
        return (bool) $this->getAttribute(self::COLUMN_IS_SYSTEM);
    }

    public function getIsSystem(): bool
    {
        return $this->isSystem();
    }

    public function isActive(): bool
    {
        return (bool) $this->getAttribute(self::COLUMN_IS_ACTIVE);
    }

    public function getIsActive(): bool
    {
        return $this->isActive();
    }

    public function setIsActive(bool $isActive): static
    {
        return $this->setAttribute(self::COLUMN_IS_ACTIVE, $isActive);
    }

    public function getStartsAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_STARTS_AT);
    }

    public function setStartsAt(?Carbon $startsAt): static
    {
        return $this->setAttribute(self::COLUMN_STARTS_AT, $startsAt);
    }

    public function getEndsAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_ENDS_AT);
    }

    public function setEndsAt(?Carbon $endsAt): static
    {
        return $this->setAttribute(self::COLUMN_ENDS_AT, $endsAt);
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
     * Active and inside its schedule now.
     */
    public function isVisible(): bool
    {
        return $this->isActive()
            && ! $this->trashed()
            && (! $this->getStartsAt() instanceof Carbon || $this->getStartsAt()->isPast())
            && (! $this->getEndsAt() instanceof Carbon || $this->getEndsAt()->isFuture());
    }

    /**
     * The products in the admin's order.
     *
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'collection_product', 'collection_id', 'product_id')
            ->withPivot(self::PIVOT_SORT)
            ->orderByPivot(self::PIVOT_SORT)
            ->orderBy('products.id');
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
     * Collections a shopper may see now: active and inside their schedule.
     *
     * @param  Builder<ProductCollection>  $query
     * @return Builder<ProductCollection>
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where(self::COLUMN_IS_ACTIVE, true)
            ->where(fn (Builder $starts) => $starts->whereNull(self::COLUMN_STARTS_AT)->orWhere(self::COLUMN_STARTS_AT, '<=', now()))
            ->where(fn (Builder $ends) => $ends->whereNull(self::COLUMN_ENDS_AT)->orWhere(self::COLUMN_ENDS_AT, '>', now()));
    }

    public function getRouteKeyName(): string
    {
        return self::COLUMN_SLUG;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('catalog')
            ->logOnly([self::COLUMN_NAME_FA, self::COLUMN_NAME_EN, self::COLUMN_SLUG, self::COLUMN_DESCRIPTION, self::COLUMN_IS_ACTIVE, self::COLUMN_STARTS_AT, self::COLUMN_ENDS_AT, self::COLUMN_SORT])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function activityLogLabel(): string
    {
        return 'کالکشن: '.$this->getNameFa();
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_IS_SYSTEM => 'boolean',
            self::COLUMN_IS_ACTIVE => 'boolean',
            self::COLUMN_STARTS_AT => 'datetime',
            self::COLUMN_ENDS_AT => 'datetime',
            self::COLUMN_SORT => 'integer',
        ];
    }
}
