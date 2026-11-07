<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\ProductFactory;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Media\Concerns\InteractsWithImagePresets;
use App\Modules\Media\Contracts\HasImagePresets;
use App\Modules\Media\Contracts\StoredImage;
use App\Modules\Shared\Contracts\HasActivityLogLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A product (roadmap D3.1). What customers buy are its variants; photos are the "gallery" image collection.
 * Shown on the storefront at /p/{slug} while active (S13).
 */
final class Product extends Model implements HasActivityLogLabel, HasImagePresets
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    use InteractsWithImagePresets;
    use LogsActivity;
    use SoftDeletes;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_NAME_FA = 'name_fa';

    public const string COLUMN_NAME_EN = 'name_en';

    public const string COLUMN_SLUG = 'slug';

    public const string COLUMN_BRAND_ID = 'brand_id';

    public const string COLUMN_PRIMARY_CATEGORY_ID = 'primary_category_id';

    public const string COLUMN_PRODUCT_TYPE = 'product_type';

    public const string COLUMN_REQUIRES_PRESCRIPTION = 'requires_prescription';

    public const string COLUMN_IRC_CODE = 'irc_code';

    public const string COLUMN_SHORT_DESCRIPTION = 'short_description';

    public const string COLUMN_DESCRIPTION = 'description';

    public const string COLUMN_INGREDIENTS = 'ingredients';

    public const string COLUMN_USAGE = 'usage';

    public const string COLUMN_WARNINGS = 'warnings';

    public const string COLUMN_SIDE_EFFECTS = 'side_effects';

    public const string COLUMN_INTERACTIONS = 'interactions';

    public const string COLUMN_STATUS = 'status';

    public const string COLUMN_SEO_TITLE = 'seo_title';

    public const string COLUMN_SEO_DESCRIPTION = 'seo_description';

    public const string COLUMN_CREATED_AT = 'created_at';

    public const string COLUMN_UPDATED_AT = 'updated_at';

    public const string COLUMN_DELETED_AT = 'deleted_at';

    /**
     * Product photos (preset "product", roadmap D14.2): several, in order; the first is the main photo.
     */
    public const string IMAGE_GALLERY = 'gallery';

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getNameFa(): string
    {
        return (string) $this->getAttribute(self::COLUMN_NAME_FA);
    }

    public function setNameFa(string $value): static
    {
        return $this->setAttribute(self::COLUMN_NAME_FA, $value);
    }

    public function getNameEn(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_NAME_EN);

        return $value === null ? null : (string) $value;
    }

    public function setNameEn(?string $value): static
    {
        return $this->setAttribute(self::COLUMN_NAME_EN, $value);
    }

    public function getSlug(): string
    {
        return (string) $this->getAttribute(self::COLUMN_SLUG);
    }

    public function setSlug(string $value): static
    {
        return $this->setAttribute(self::COLUMN_SLUG, $value);
    }

    public function getIrcCode(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_IRC_CODE);

        return $value === null ? null : (string) $value;
    }

    public function setIrcCode(?string $value): static
    {
        return $this->setAttribute(self::COLUMN_IRC_CODE, $value);
    }

    public function getShortDescription(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_SHORT_DESCRIPTION);

        return $value === null ? null : (string) $value;
    }

    public function setShortDescription(?string $value): static
    {
        return $this->setAttribute(self::COLUMN_SHORT_DESCRIPTION, $value);
    }

    public function getDescription(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_DESCRIPTION);

        return $value === null ? null : (string) $value;
    }

    public function setDescription(?string $value): static
    {
        return $this->setAttribute(self::COLUMN_DESCRIPTION, $value);
    }

    public function getIngredients(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_INGREDIENTS);

        return $value === null ? null : (string) $value;
    }

    public function setIngredients(?string $value): static
    {
        return $this->setAttribute(self::COLUMN_INGREDIENTS, $value);
    }

    public function getUsage(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_USAGE);

        return $value === null ? null : (string) $value;
    }

    public function setUsage(?string $value): static
    {
        return $this->setAttribute(self::COLUMN_USAGE, $value);
    }

    public function getWarnings(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_WARNINGS);

        return $value === null ? null : (string) $value;
    }

    public function setWarnings(?string $value): static
    {
        return $this->setAttribute(self::COLUMN_WARNINGS, $value);
    }

    public function getSideEffects(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_SIDE_EFFECTS);

        return $value === null ? null : (string) $value;
    }

    public function setSideEffects(?string $value): static
    {
        return $this->setAttribute(self::COLUMN_SIDE_EFFECTS, $value);
    }

    public function getInteractions(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_INTERACTIONS);

        return $value === null ? null : (string) $value;
    }

    public function setInteractions(?string $value): static
    {
        return $this->setAttribute(self::COLUMN_INTERACTIONS, $value);
    }

    public function getSeoTitle(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_SEO_TITLE);

        return $value === null ? null : (string) $value;
    }

    public function setSeoTitle(?string $value): static
    {
        return $this->setAttribute(self::COLUMN_SEO_TITLE, $value);
    }

    public function getSeoDescription(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_SEO_DESCRIPTION);

        return $value === null ? null : (string) $value;
    }

    public function setSeoDescription(?string $value): static
    {
        return $this->setAttribute(self::COLUMN_SEO_DESCRIPTION, $value);
    }

    public function getBrandId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_BRAND_ID);
    }

    public function setBrandId(int $brandId): static
    {
        return $this->setAttribute(self::COLUMN_BRAND_ID, $brandId);
    }

    public function getPrimaryCategoryId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_PRIMARY_CATEGORY_ID);
    }

    public function setPrimaryCategoryId(int $categoryId): static
    {
        return $this->setAttribute(self::COLUMN_PRIMARY_CATEGORY_ID, $categoryId);
    }

    public function getProductType(): ProductType
    {
        return $this->getAttribute(self::COLUMN_PRODUCT_TYPE);
    }

    public function setProductType(ProductType $type): static
    {
        return $this->setAttribute(self::COLUMN_PRODUCT_TYPE, $type);
    }

    public function requiresPrescription(): bool
    {
        return (bool) $this->getAttribute(self::COLUMN_REQUIRES_PRESCRIPTION);
    }

    public function getRequiresPrescription(): bool
    {
        return $this->requiresPrescription();
    }

    public function setRequiresPrescription(bool $requiresPrescription): static
    {
        return $this->setAttribute(self::COLUMN_REQUIRES_PRESCRIPTION, $requiresPrescription);
    }

    public function getStatus(): ProductStatus
    {
        return $this->getAttribute(self::COLUMN_STATUS);
    }

    public function setStatus(ProductStatus $status): static
    {
        return $this->setAttribute(self::COLUMN_STATUS, $status);
    }

    public function isActive(): bool
    {
        return $this->getStatus() === ProductStatus::Active;
    }

    /**
     * Whether this product may be activated for sale: not a prescription medicine, and its type is not listed in
     * config('catalog.unsellable_product_types') (roadmap D0 rule 9).
     */
    public function isSellableType(): bool
    {
        return ! $this->requiresPrescription() && $this->getProductType()->isSellable();
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
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, self::COLUMN_BRAND_ID)->withTrashed();
    }

    public function getBrand(): ?Brand
    {
        return $this->getRelationValue('brand');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function primaryCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, self::COLUMN_PRIMARY_CATEGORY_ID)->withTrashed();
    }

    public function getPrimaryCategory(): ?Category
    {
        return $this->getRelationValue('primaryCategory');
    }

    /**
     * Extra categories besides the primary one.
     *
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    /**
     * @return Collection<int, Category>
     */
    public function getCategories(): Collection
    {
        return $this->getRelationValue('categories');
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy(ProductVariant::COLUMN_ID);
    }

    /**
     * @return Collection<int, ProductVariant>
     */
    public function getVariants(): Collection
    {
        return $this->getRelationValue('variants');
    }

    /**
     * @return HasMany<ProductFaq, $this>
     */
    public function faqs(): HasMany
    {
        return $this->hasMany(ProductFaq::class)->orderBy(ProductFaq::COLUMN_SORT)->orderBy(ProductFaq::COLUMN_ID);
    }

    /**
     * @return Collection<int, ProductFaq>
     */
    public function getFaqs(): Collection
    {
        return $this->getRelationValue('faqs');
    }

    /**
     * Links from this product to related, alternative and frequently-bought products.
     *
     * Named productRelations() because Eloquent already uses "relations" for loaded relationships.
     *
     * @return HasMany<ProductRelation, $this>
     */
    public function productRelations(): HasMany
    {
        return $this->hasMany(ProductRelation::class);
    }

    /**
     * @return Collection<int, ProductRelation>
     */
    public function getProductRelations(): Collection
    {
        return $this->getRelationValue('productRelations');
    }

    /**
     * @return array<string, string>
     */
    public function imagePresets(): array
    {
        return [self::IMAGE_GALLERY => 'product'];
    }

    /**
     * @return list<string>
     */
    public function multipleImageCollections(): array
    {
        return [self::IMAGE_GALLERY];
    }

    /**
     * The main photo: the first in the gallery.
     */
    public function getMainImage(): ?StoredImage
    {
        return $this->image(self::IMAGE_GALLERY);
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(self::COLUMN_STATUS, ProductStatus::Active);
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
                self::COLUMN_NAME_FA, self::COLUMN_NAME_EN, self::COLUMN_SLUG, self::COLUMN_BRAND_ID, self::COLUMN_PRIMARY_CATEGORY_ID,
                self::COLUMN_PRODUCT_TYPE, self::COLUMN_REQUIRES_PRESCRIPTION, self::COLUMN_IRC_CODE, self::COLUMN_SHORT_DESCRIPTION,
                self::COLUMN_DESCRIPTION, self::COLUMN_INGREDIENTS, self::COLUMN_USAGE, self::COLUMN_WARNINGS, self::COLUMN_SIDE_EFFECTS,
                self::COLUMN_INTERACTIONS, self::COLUMN_STATUS, self::COLUMN_SEO_TITLE, self::COLUMN_SEO_DESCRIPTION,
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function activityLogLabel(): string
    {
        return 'محصول: '.$this->getNameFa();
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_BRAND_ID => 'integer',
            self::COLUMN_PRIMARY_CATEGORY_ID => 'integer',
            self::COLUMN_PRODUCT_TYPE => ProductType::class,
            self::COLUMN_REQUIRES_PRESCRIPTION => 'boolean',
            self::COLUMN_STATUS => ProductStatus::class,
        ];
    }
}
