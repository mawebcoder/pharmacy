<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\ProductFactory;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\Search\ProductDocument;
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
use Illuminate\Support\Collection as BaseCollection;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A product (roadmap D3.1). What customers buy are its variants; photos are the "gallery" image collection.
 * Shown on the storefront at /products/{slug} while active (S13).
 */
final class Product extends Model implements HasActivityLogLabel, HasImagePresets
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    use InteractsWithImagePresets;
    use LogsActivity;
    use Searchable;
    use SoftDeletes;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_NAME_FA = 'name_fa';

    public const string COLUMN_NAME_EN = 'name_en';

    public const string COLUMN_SLUG = 'slug';

    public const string COLUMN_BRAND_ID = 'brand_id';

    public const string COLUMN_COMMISSION_PERCENT = 'commission_percent';

    public const string COLUMN_HARAJ_PERCENT = 'haraj_percent';

    public const string COLUMN_PRIMARY_CATEGORY_ID = 'primary_category_id';

    public const string COLUMN_PRIMARY_SUBCATEGORY_ID = 'primary_subcategory_id';

    public const string COLUMN_DEFAULT_VARIANT_ID = 'default_variant_id';

    public const string COLUMN_PRODUCT_TYPE = 'product_type';

    public const string COLUMN_IRC_CODE = 'irc_code';

    public const string COLUMN_MAX_PER_ORDER = 'max_per_order';

    public const string COLUMN_MIN_SHELF_LIFE_MONTHS = 'min_shelf_life_months';

    /**
     * The product's Tipax package content (a Tipax id from tipax_references; null: the store default) and whether it
     * is liquid or fragile (owner, 2026-11-19: per product, not per SKU).
     */
    public const string COLUMN_TIPAX_CONTENT_ID = 'tipax_content_id';

    public const string COLUMN_IS_LIQUID = 'is_liquid';

    public const string COLUMN_IS_FRAGILE = 'is_fragile';

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

    /**
     * Most units of one SKU in one order for this product; null falls back to the store setting (the SKU's own limit
     * wins, S21).
     */
    public function getMaxPerOrder(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_MAX_PER_ORDER);

        return $value === null ? null : (int) $value;
    }

    public function setMaxPerOrder(?int $value): static
    {
        return $this->setAttribute(self::COLUMN_MAX_PER_ORDER, $value);
    }

    /**
     * The shelf-life guarantee shown on the product page, in months (owner, 2026-11-16); null shows none.
     */
    public function getMinShelfLifeMonths(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_MIN_SHELF_LIFE_MONTHS);

        return $value === null ? null : (int) $value;
    }

    public function setMinShelfLifeMonths(?int $value): static
    {
        return $this->setAttribute(self::COLUMN_MIN_SHELF_LIFE_MONTHS, $value);
    }

    public function getTipaxContentId(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_TIPAX_CONTENT_ID);

        return $value === null ? null : (int) $value;
    }

    public function setTipaxContentId(?int $value): static
    {
        return $this->setAttribute(self::COLUMN_TIPAX_CONTENT_ID, $value);
    }

    public function isLiquid(): bool
    {
        return (bool) $this->getAttribute(self::COLUMN_IS_LIQUID);
    }

    public function setIsLiquid(bool $value): static
    {
        return $this->setAttribute(self::COLUMN_IS_LIQUID, $value);
    }

    public function isFragile(): bool
    {
        return (bool) $this->getAttribute(self::COLUMN_IS_FRAGILE);
    }

    public function setIsFragile(bool $value): static
    {
        return $this->setAttribute(self::COLUMN_IS_FRAGILE, $value);
    }

    /**
     * «زودمصرف» when its default SKU's selling supplier has near-expiry stock (owner, 2026-11-17: per supplier, not a
     * product switch). Needs the variants loaded.
     */
    public function isNearExpiry(): bool
    {
        return $this->getDefaultVariant()?->isNearExpiry() ?? false;
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

    /**
     * Commission % (roadmap D3.2): the product's own, for all its SKUs; overrides the brand's. Null uses the brand's.
     */
    public function getCommissionPercent(): ?float
    {
        $value = $this->getAttribute(self::COLUMN_COMMISSION_PERCENT);

        return $value === null ? null : (float) $value;
    }

    public function setCommissionPercent(?float $commissionPercent): static
    {
        return $this->setAttribute(self::COLUMN_COMMISSION_PERCENT, $commissionPercent);
    }

    /**
     * The haraj percentage its SKUs inherit (owner, 2026-11-17); a SKU's own percentage overrides it.
     */
    public function getHarajPercent(): ?float
    {
        $value = $this->getAttribute(self::COLUMN_HARAJ_PERCENT);

        return $value === null ? null : (float) $value;
    }

    public function setHarajPercent(?float $value): static
    {
        return $this->setAttribute(self::COLUMN_HARAJ_PERCENT, $value);
    }

    /**
     * Null for a product imported without one (owner, 2026-11-27); it cannot be active until staff set it.
     */
    public function getBrandId(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_BRAND_ID);

        return $value === null ? null : (int) $value;
    }

    public function setBrandId(?int $brandId): static
    {
        return $this->setAttribute(self::COLUMN_BRAND_ID, $brandId);
    }

    /**
     * Null for a product imported without a subcategory (owner, 2026-11-27).
     */
    public function getPrimaryCategoryId(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_PRIMARY_CATEGORY_ID);

        return $value === null ? null : (int) $value;
    }

    public function setPrimaryCategoryId(?int $categoryId): static
    {
        return $this->setAttribute(self::COLUMN_PRIMARY_CATEGORY_ID, $categoryId);
    }

    /**
     * The main subcategory, one of the main category's (optional; owner, 2026-11-15).
     */
    public function getPrimarySubcategoryId(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_PRIMARY_SUBCATEGORY_ID);

        return $value === null ? null : (int) $value;
    }

    public function setPrimarySubcategoryId(?int $subcategoryId): static
    {
        return $this->setAttribute(self::COLUMN_PRIMARY_SUBCATEGORY_ID, $subcategoryId);
    }

    /**
     * The variant the admin chose as default; see getDefaultVariant() for the one actually shown.
     */
    public function getDefaultVariantId(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_DEFAULT_VARIANT_ID);

        return $value === null ? null : (int) $value;
    }

    public function setDefaultVariantId(?int $variantId): static
    {
        return $this->setAttribute(self::COLUMN_DEFAULT_VARIANT_ID, $variantId);
    }

    public function getProductType(): ProductType
    {
        return $this->getAttribute(self::COLUMN_PRODUCT_TYPE);
    }

    public function setProductType(ProductType $type): static
    {
        return $this->setAttribute(self::COLUMN_PRODUCT_TYPE, $type);
    }

    /**
     * A prescription medicine: its type is «نسخه‌ای» (owner, 2026-11-16; no separate switch).
     */
    public function requiresPrescription(): bool
    {
        return $this->getProductType() === ProductType::Rx;
    }

    public function getStatus(): ProductStatus
    {
        return $this->getAttribute(self::COLUMN_STATUS);
    }

    public function setStatus(ProductStatus $status): static
    {
        return $this->setAttribute(self::COLUMN_STATUS, $status);
    }

    /**
     * Active, with its brand, category and subcategory (a product imported without them never reaches the shop).
     */
    public function isActive(): bool
    {
        return $this->getStatus() === ProductStatus::Active
            && $this->getBrandId() !== null
            && $this->getPrimaryCategoryId() !== null
            && $this->getPrimarySubcategoryId() !== null;
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
     * @return BelongsTo<Subcategory, $this>
     */
    public function primarySubcategory(): BelongsTo
    {
        return $this->belongsTo(Subcategory::class, self::COLUMN_PRIMARY_SUBCATEGORY_ID)->withTrashed();
    }

    public function getPrimarySubcategory(): ?Subcategory
    {
        return $this->getRelationValue('primarySubcategory');
    }

    /**
     * Extra subcategories besides the main one.
     *
     * @return BelongsToMany<Subcategory, $this>
     */
    public function subcategories(): BelongsToMany
    {
        return $this->belongsToMany(Subcategory::class);
    }

    /**
     * @return Collection<int, Subcategory>
     */
    public function getSubcategories(): Collection
    {
        return $this->getRelationValue('subcategories');
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy(ProductVariant::COLUMN_SORT)->orderBy(ProductVariant::COLUMN_ID);
    }

    /**
     * @return Collection<int, ProductVariant>
     */
    public function getVariants(): Collection
    {
        return $this->getRelationValue('variants');
    }

    /**
     * The SKU shown on cards and in search and added by «افزودن به سبد» (decided 2026-11-07, S12): the admin's
     * choice while it is active and in stock; otherwise the first active, in-stock variant in the admin's order;
     * when nothing is in stock, the admin's choice or else the first active variant (shown as «ناموجود»).
     * Only the choice is stored, so the default returns to it by itself once it is back in stock.
     */
    public function getDefaultVariant(): ?ProductVariant
    {
        $active = $this->getVariants()->filter(fn (ProductVariant $variant): bool => $variant->isActive() && ! $variant->trashed());
        $chosen = $active->first(fn (ProductVariant $variant): bool => $variant->getId() === $this->getDefaultVariantId());

        if ($chosen?->isInStock()) {
            return $chosen;
        }

        return $active->first(fn (ProductVariant $variant): bool => $variant->isInStock()) ?? $chosen ?? $active->first();
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
     * Chosen attribute values, e.g. «کرم» for «شکل محصول».
     *
     * @return BelongsToMany<AttributeValue, $this>
     */
    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'product_attribute_value');
    }

    /**
     * @return Collection<int, AttributeValue>
     */
    public function getAttributeValues(): Collection
    {
        return $this->getRelationValue('attributeValues');
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return $this->getRelationValue('tags');
    }

    /**
     * @return BelongsToMany<ProductCollection, $this>
     */
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(ProductCollection::class, 'collection_product', 'product_id', 'collection_id');
    }

    /**
     * @return Collection<int, ProductCollection>
     */
    public function getCollections(): Collection
    {
        return $this->getRelationValue('collections');
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
        // Activation requires them; kept here so a product without brand or category never reaches the shop.
        return $query->where(self::COLUMN_STATUS, ProductStatus::Active)
            ->whereNotNull(self::COLUMN_BRAND_ID)
            ->whereNotNull(self::COLUMN_PRIMARY_CATEGORY_ID)
            ->whereNotNull(self::COLUMN_PRIMARY_SUBCATEGORY_ID);
    }

    /**
     * Products for sale, as in search (shouldBeSearchable): active, not prescription-only, a sellable type, with an
     * active SKU.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeSellable(Builder $query): Builder
    {
        /** @var list<string> $unsellable */
        $unsellable = config('catalog.unsellable_product_types', []);

        return $query
            ->active()
            ->where(self::COLUMN_PRODUCT_TYPE, '!=', ProductType::Rx)
            ->whereNotIn(self::COLUMN_PRODUCT_TYPE, $unsellable)
            ->whereHas('variants', fn (Builder $variants) => $variants->where(ProductVariant::COLUMN_IS_ACTIVE, true));
    }

    /**
     * Meilisearch index name, with Scout's prefix (tests use their own prefix per process).
     */
    public function searchableAs(): string
    {
        return config('scout.prefix').'products';
    }

    /**
     * Every product that is not deleted is in the index, shown or not (owner, 2026-11-27); the storefront filters on
     * is_visible (isShownOnStorefront).
     */
    public function shouldBeSearchable(): bool
    {
        return ! $this->trashed();
    }

    /**
     * Whether shoppers see the product in lists and search: active, a sellable type, with an active variant (the
     * document's is_visible; scopeSellable in SQL). Uses the loaded variants when present, so a bulk reindex asks no
     * query per product.
     */
    public function isShownOnStorefront(): bool
    {
        if (! $this->isActive() || $this->trashed() || ! $this->isSellableType()) {
            return false;
        }

        return $this->relationLoaded('variants')
            ? $this->getVariants()->contains(fn (ProductVariant $variant): bool => $variant->isActive() && ! $variant->trashed())
            : $this->variants()->active()->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return ProductDocument::for($this->loadMissing(ProductDocument::RELATIONS));
    }

    /**
     * @param  BaseCollection<int, Product>  $models
     * @return BaseCollection<int, Product>
     */
    public function makeSearchableUsing(BaseCollection $models): BaseCollection
    {
        return $models instanceof Collection ? $models->loadMissing(ProductDocument::RELATIONS) : $models;
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
                self::COLUMN_NAME_FA, self::COLUMN_NAME_EN, self::COLUMN_SLUG, self::COLUMN_BRAND_ID, self::COLUMN_COMMISSION_PERCENT, self::COLUMN_HARAJ_PERCENT, self::COLUMN_PRIMARY_CATEGORY_ID, self::COLUMN_PRIMARY_SUBCATEGORY_ID, self::COLUMN_DEFAULT_VARIANT_ID,
                self::COLUMN_PRODUCT_TYPE, self::COLUMN_IRC_CODE, self::COLUMN_MAX_PER_ORDER, self::COLUMN_MIN_SHELF_LIFE_MONTHS,
                self::COLUMN_TIPAX_CONTENT_ID, self::COLUMN_IS_LIQUID, self::COLUMN_IS_FRAGILE, self::COLUMN_SHORT_DESCRIPTION,
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
            self::COLUMN_COMMISSION_PERCENT => 'decimal:2',
            self::COLUMN_HARAJ_PERCENT => 'decimal:2',
            self::COLUMN_PRIMARY_CATEGORY_ID => 'integer',
            self::COLUMN_PRIMARY_SUBCATEGORY_ID => 'integer',
            self::COLUMN_DEFAULT_VARIANT_ID => 'integer',
            self::COLUMN_PRODUCT_TYPE => ProductType::class,
            self::COLUMN_MAX_PER_ORDER => 'integer',
            self::COLUMN_MIN_SHELF_LIFE_MONTHS => 'integer',
            self::COLUMN_TIPAX_CONTENT_ID => 'integer',
            self::COLUMN_IS_LIQUID => 'boolean',
            self::COLUMN_IS_FRAGILE => 'boolean',
            self::COLUMN_STATUS => ProductStatus::class,
        ];
    }
}
