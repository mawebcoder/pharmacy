<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Data\ProductData;
use App\Modules\Catalog\Enums\PricingInputChange;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Events\PricingInputsChanged;
use App\Modules\Catalog\Exceptions\AttributeChangeRefused;
use App\Modules\Catalog\Exceptions\ProductChangeRefused;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Subcategory;
use App\Modules\Catalog\Queries\VariantPricingInputs;
use App\Modules\Catalog\Support\CatalogSlug;
use App\Modules\Catalog\Support\ProductActivation;
use App\Modules\Catalog\Support\ProductAttributes;
use Illuminate\Support\Facades\DB;

/**
 * Updates a product with its extra categories, attribute values and tags. Whenever the result is active, the activation checks run (ProductActivation), so e.g. changing
 * an active product into a prescription medicine is refused. The slug only changes when an admin types a new one.
 */
final class UpdateProduct
{
    /**
     * @throws ProductChangeRefused
     * @throws AttributeChangeRefused
     */
    #[\NoDiscard]
    public function __invoke(Product $product, ProductData $data): Product
    {
        return DB::transaction(function () use ($product, $data): Product {
            self::fill($product, $data)->setStatus($data->status);
            $commissionMayChange = $product->isDirty([Product::COLUMN_COMMISSION_PERCENT, Product::COLUMN_BRAND_ID]);
            $harajChanged = $product->exists && $product->isDirty(Product::COLUMN_HARAJ_PERCENT);

            if ($data->status === ProductStatus::Active) {
                ProductActivation::assertAllowed($product);
            }

            ProductAttributes::assertValid($data->attributeValueIds);

            $product->save();
            self::syncLinks($product, $data);

            if ($commissionMayChange) {
                event(new PricingInputsChanged(app(VariantPricingInputs::class)->variantIdsOfProduct($product->getId()), PricingInputChange::Commission, self::adminId()));
            } elseif ($harajChanged) {
                // The product's haraj percentage reprices its SKUs that inherit it (owner, 2026-11-17).
                event(new PricingInputsChanged(app(VariantPricingInputs::class)->variantIdsOfProduct($product->getId()), PricingInputChange::Haraj, self::adminId()));
            }

            return $product;
        });
    }

    /**
     * Extra subcategories (without the main one), attribute values and tags. Products are placed in subcategories
     * only (owner, 2026-11-15), so extra categories are cleared.
     */
    public static function syncLinks(Product $product, ProductData $data): void
    {
        $product->categories()->sync([]);
        $product->subcategories()->sync(array_values(array_diff($data->subcategoryIds, array_filter([$data->primarySubcategoryId]))));
        $product->attributeValues()->sync($data->attributeValueIds);
        $product->tags()->sync($data->tagIds);
    }

    /**
     * Copies the data onto the product, except the status. Products belong to subcategories (owner, 2026-11-15): the
     * main category is the main subcategory's. Brand and subcategory may be empty for an imported product (owner,
     * 2026-11-27): the admin form requires them, and the product cannot be active without them.
     */
    public static function fill(Product $product, ProductData $data): Product
    {
        $categoryId = $data->primarySubcategoryId === null
            ? null
            : Subcategory::query()->whereKey($data->primarySubcategoryId)->value(Subcategory::COLUMN_CATEGORY_ID);

        if ($data->primarySubcategoryId !== null && $categoryId === null) {
            throw ProductChangeRefused::subcategoryRequired();
        }

        $slug = $product->exists && ($data->slug === null || $data->slug === $product->getSlug())
            ? $product->getSlug()
            : CatalogSlug::for(Product::class, $data->slug, $data->nameFa, $data->nameEn, $product->exists ? $product->getId() : null);

        return $product
            ->setNameFa($data->nameFa)
            ->setNameEn($data->nameEn)
            ->setSlug($slug)
            ->setBrandId($data->brandId)
            ->setCommissionPercent($data->commissionPercent)
            ->setPrimaryCategoryId($categoryId === null ? null : (int) $categoryId)
            ->setPrimarySubcategoryId($data->primarySubcategoryId)
            ->setProductType($data->productType)
            ->setIrcCode($data->ircCode)
            ->setMaxPerOrder($data->maxPerOrder)
            ->setMinShelfLifeMonths($data->minShelfLifeMonths)
            ->setHarajPercent($data->harajPercent)
            ->setTipaxContentId($data->tipaxContentId)
            ->setIsLiquid($data->isLiquid)
            ->setIsFragile($data->isFragile)
            ->setShortDescription($data->shortDescription)
            ->setDescription($data->description)
            ->setIngredients($data->ingredients)
            ->setUsage($data->usage)
            ->setWarnings($data->warnings)
            ->setSideEffects($data->sideEffects)
            ->setInteractions($data->interactions)
            ->setSeoTitle($data->seoTitle)
            ->setSeoDescription($data->seoDescription);
    }

    /**
     * The signed-in admin, for the price history.
     */
    public static function adminId(): ?int
    {
        $id = auth('admin')->id();

        return $id === null ? null : (int) $id;
    }
}
