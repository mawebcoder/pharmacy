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

    public const string COLUMN_PRIMARY_CATEGORY_ID = 'primary_category_id';

    public const string COLUMN_DEFAULT_VARIANT_ID = 'default_variant_id';

    public const string COLUMN_PRODUCT_TYPE = 'product_type';

    public const string COLUMN_REQUIRES_PRESCRIPTION = 'requires_prescription';

    public const string COLUMN_IRC_CODE = 'irc_code';

    public const string COLUMN_MAX_PER_ORDER = 'max_per_order';

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
     * wins, S19).
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
        return $query->where(self::COLUMN_STATUS, ProductStatus::Active);
    }

    /**
     * Meilisearch index name, with Scout's prefix (tests use their own prefix per process).
     */
    public function searchableAs(): string
    {
        return config('scout.prefix').'products';
    }

    /**
     * Only products for sale are searchable: active, a sellable type, with an active variant.
     */
    public function shouldBeSearchable(): bool
    {
        return $this->isActive() && ! $this->trashed() && $this->isSellableType() && $this->variants()->active()->exists();
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
        return $models instanceof Collection ? $models->load(ProductDocument::RELATIONS) : $models;
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
                self::COLUMN_NAME_FA, self::COLUMN_NAME_EN, self::COLUMN_SLUG, self::COLUMN_BRAND_ID, self::COLUMN_COMMISSION_PERCENT, self::COLUMN_PRIMARY_CATEGORY_ID, self::COLUMN_DEFAULT_VARIANT_ID,
                self::COLUMN_PRODUCT_TYPE, self::COLUMN_REQUIRES_PRESCRIPTION, self::COLUMN_IRC_CODE, self::COLUMN_MAX_PER_ORDER, self::COLUMN_SHORT_DESCRIPTION,
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
            self::COLUMN_PRIMARY_CATEGORY_ID => 'integer',
            self::COLUMN_DEFAULT_VARIANT_ID => 'integer',
            self::COLUMN_PRODUCT_TYPE => ProductType::class,
            self::COLUMN_REQUIRES_PRESCRIPTION => 'boolean',
            self::COLUMN_MAX_PER_ORDER => 'integer',
            self::COLUMN_STATUS => ProductStatus::class,
        ];
    }
}
