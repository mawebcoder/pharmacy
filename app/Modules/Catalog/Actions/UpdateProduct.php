<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Data\ProductData;
use App\Modules\Catalog\Enums\PricingInputChange;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\Events\PricingInputsChanged;
use App\Modules\Catalog\Exceptions\AttributeChangeRefused;
use App\Modules\Catalog\Exceptions\ProductChangeRefused;
use App\Modules\Catalog\Models\Product;
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

            if ($data->status === ProductStatus::Active) {
                ProductActivation::assertAllowed($product);
            }

            ProductAttributes::assertValid($data->attributeValueIds);

            $product->save();
            self::syncLinks($product, $data);

            if ($commissionMayChange) {
                event(new PricingInputsChanged(app(VariantPricingInputs::class)->variantIdsOfProduct($product->getId()), PricingInputChange::Commission, self::adminId()));
            }

            return $product;
        });
    }

    /**
     * Extra categories (without the primary one), attribute values and tags.
     */
    public static function syncLinks(Product $product, ProductData $data): void
    {
        $product->categories()->sync(array_values(array_diff($data->categoryIds, [$data->primaryCategoryId])));
        $product->attributeValues()->sync($data->attributeValueIds);
        $product->tags()->sync($data->tagIds);
    }

    /**
     * Copies the data onto the product, except the status.
     */
    public static function fill(Product $product, ProductData $data): Product
    {
        $slug = $product->exists && ($data->slug === null || $data->slug === $product->getSlug())
            ? $product->getSlug()
            : CatalogSlug::for(Product::class, $data->slug, $data->nameFa, $data->nameEn, $product->exists ? $product->getId() : null);

        return $product
            ->setNameFa($data->nameFa)
            ->setNameEn($data->nameEn)
            ->setSlug($slug)
            ->setBrandId($data->brandId)
            ->setCommissionPercent($data->commissionPercent)
            ->setPrimaryCategoryId($data->primaryCategoryId)
            ->setProductType($data->productType)
            // Prescription medicines always need a prescription.
            ->setRequiresPrescription($data->requiresPrescription || $data->productType === ProductType::Rx)
            ->setIrcCode($data->ircCode)
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
