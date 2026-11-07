<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Data\ProductData;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\Exceptions\ProductChangeRefused;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Support\CatalogSlug;
use App\Modules\Catalog\Support\ProductActivation;
use Illuminate\Support\Facades\DB;

/**
 * Updates a product. Whenever the result is active, the activation checks run (ProductActivation), so e.g. changing
 * an active product into a prescription medicine is refused. The slug only changes when an admin types a new one.
 */
final class UpdateProduct
{
    /**
     * @throws ProductChangeRefused
     */
    #[\NoDiscard]
    public function __invoke(Product $product, ProductData $data): Product
    {
        return DB::transaction(function () use ($product, $data): Product {
            self::fill($product, $data)->setStatus($data->status);

            if ($data->status === ProductStatus::Active) {
                ProductActivation::assertAllowed($product);
            }

            $product->save();
            $product->categories()->sync(array_values(array_diff($data->categoryIds, [$data->primaryCategoryId])));

            return $product;
        });
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
}
