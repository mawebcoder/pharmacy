<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\CategoryFactory;
use App\Modules\Catalog\Queries\CategoryTree;
use App\Modules\Catalog\Search\NameDocument;
use App\Modules\Media\Concerns\InteractsWithImagePresets;
use App\Modules\Media\Contracts\HasImagePresets;
use App\Modules\Media\Contracts\StoredImage;
use App\Modules\Shared\Contracts\HasActivityLogLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A product category (roadmap D3.1): the top level, ordered by "sort"; its subcategories are Subcategory rows in their
 * own table (owner, 2026-11-15). Shown on the storefront at /categories/{slug} while active.
 */
final class Category extends Model implements HasActivityLogLabel, HasImagePresets
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    use InteractsWithImagePresets;
    use LogsActivity;
    use Searchable;
    use SoftDeletes;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_NAME_FA = 'name_fa';

    public const string COLUMN_NAME_EN = 'name_en';

    public const string COLUMN_SLUG = 'slug';

    public const string COLUMN_DESCRIPTION = 'description';

    public const string COLUMN_SORT = 'sort';

    public const string COLUMN_IS_ACTIVE = 'is_active';

    public const string COLUMN_SEO_TITLE = 'seo_title';

    public const string COLUMN_SEO_DESCRIPTION = 'seo_description';

    public const string COLUMN_CREATED_AT = 'created_at';

    public const string COLUMN_UPDATED_AT = 'updated_at';

    public const string COLUMN_DELETED_AT = 'deleted_at';

    /**
     * Image collections (roadmap D14.2): the icon, and the banner at the top of the category page as separate
     * desktop/tablet and mobile artwork (decided 2026-11-07 in S09).
     */
    public const string IMAGE_ICON = 'icon';

    public const string IMAGE_BANNER_DESKTOP = 'banner_desktop';

    public const string IMAGE_BANNER_MOBILE = 'banner_mobile';

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

    public function getSort(): int
    {
        return (int) $this->getAttribute(self::COLUMN_SORT);
    }

    public function setSort(int $sort): static
    {
        return $this->setAttribute(self::COLUMN_SORT, $sort);
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

    public function getSeoTitle(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_SEO_TITLE);

        return $value === null ? null : (string) $value;
    }

    public function setSeoTitle(?string $seoTitle): static
    {
        return $this->setAttribute(self::COLUMN_SEO_TITLE, $seoTitle);
    }

    public function getSeoDescription(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_SEO_DESCRIPTION);

        return $value === null ? null : (string) $value;
    }

    public function setSeoDescription(?string $seoDescription): static
    {
        return $this->setAttribute(self::COLUMN_SEO_DESCRIPTION, $seoDescription);
    }

    public function getCreatedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_CREATED_AT);
    }

    public function getUpdatedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_UPDATED_AT);
    }

    public function getDeletedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_DELETED_AT);
    }

    /**
     * @return HasMany<Subcategory, $this>
     */
    public function subcategories(): HasMany
    {
        return $this->hasMany(Subcategory::class)->orderBy(Subcategory::COLUMN_SORT)->orderBy(Subcategory::COLUMN_ID);
    }

    /**
     * @return Collection<int, Subcategory>
     */
    public function getSubcategories(): Collection
    {
        return $this->getRelationValue('subcategories');
    }

    /**
     * Whether the storefront shows this category: active and not deleted.
     */
    public function isShownOnStorefront(): bool
    {
        return $this->isActive() && ! $this->trashed();
    }

    /**
     * The storefront page, e.g. /categories/supplements.
     */
    public function url(): string
    {
        return route('category.show', $this->getSlug());
    }

    /**
     * Products whose primary category this is.
     *
     * @return HasMany<Product, $this>
     */
    public function primaryProducts(): HasMany
    {
        return $this->hasMany(Product::class, Product::COLUMN_PRIMARY_CATEGORY_ID);
    }

    /**
     * Products listed in this category as an extra category.
     *
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    /**
     * Whether any (not deleted) product uses this category, as primary or extra category; such a category cannot
     * be deleted (DeleteCategory), nor one that has subcategories.
     */
    public function hasProducts(): bool
    {
        return $this->primaryProducts()->exists() || $this->products()->exists();
    }

    /**
     * @param  Builder<Category>  $query
     * @return Builder<Category>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(self::COLUMN_IS_ACTIVE, true);
    }

    /**
     * In their sort order (menu, admin).
     *
     * @param  Builder<Category>  $query
     * @return Builder<Category>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy(self::COLUMN_SORT)->orderBy(self::COLUMN_ID);
    }

    /**
     * @return array<string, string>
     */
    public function imagePresets(): array
    {
        return [
            self::IMAGE_ICON => 'category_icon',
            self::IMAGE_BANNER_DESKTOP => 'category_banner_desktop',
            self::IMAGE_BANNER_MOBILE => 'category_banner_mobile',
        ];
    }

    public function getIcon(): ?StoredImage
    {
        return $this->image(self::IMAGE_ICON);
    }

    public function getBannerDesktop(): ?StoredImage
    {
        return $this->image(self::IMAGE_BANNER_DESKTOP);
    }

    public function getBannerMobile(): ?StoredImage
    {
        return $this->image(self::IMAGE_BANNER_MOBILE);
    }

    public function getRouteKeyName(): string
    {
        return self::COLUMN_SLUG;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('catalog')
            ->logOnly([
                self::COLUMN_NAME_FA, self::COLUMN_NAME_EN, self::COLUMN_SLUG, self::COLUMN_DESCRIPTION,
                self::COLUMN_SORT, self::COLUMN_IS_ACTIVE, self::COLUMN_SEO_TITLE, self::COLUMN_SEO_DESCRIPTION,
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * Meilisearch index name, with Scout's prefix (tests use their own prefix per process).
     */
    public function searchableAs(): string
    {
        return config('scout.prefix').'categories';
    }

    /**
     * Only active categories are in the search panel's index (owner, 2026-11-15).
     */
    public function shouldBeSearchable(): bool
    {
        return $this->isActive() && ! $this->trashed();
    }

    /**
     * @return array{id: int, name_fa: string, name_en: string}
     */
    public function toSearchableArray(): array
    {
        return NameDocument::for($this->getId(), $this->getNameFa(), $this->getNameEn());
    }

    public function activityLogLabel(): string
    {
        return 'دسته‌بندی: '.$this->getNameFa();
    }

    /**
     * The cached storefront tree is rebuilt after any change.
     */
    protected static function booted(): void
    {
        self::saved(fn () => CategoryTree::forget());
        self::deleted(fn () => CategoryTree::forget());
        self::restored(fn () => CategoryTree::forget());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_SORT => 'integer',
            self::COLUMN_IS_ACTIVE => 'boolean',
        ];
    }
}
