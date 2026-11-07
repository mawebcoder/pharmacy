<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Data\BrandData;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Support\BrandSlug;

/**
 * Updates a brand. The slug only changes when an admin types a new one, so existing links keep working.
 */
final class UpdateBrand
{
    #[\NoDiscard]
    public function __invoke(Brand $brand, BrandData $data): Brand
    {
        $slug = $data->slug === null || $data->slug === $brand->getSlug()
            ? $brand->getSlug()
            : BrandSlug::for($data->slug, $data->nameFa, $data->nameEn, $brand->getId());

        $brand
            ->setNameFa($data->nameFa)
            ->setNameEn($data->nameEn)
            ->setSlug($slug)
            ->setCountry($data->country)
            ->setDescription($data->description)
            ->setIsActive($data->isActive)
            ->setSeoTitle($data->seoTitle)
            ->setSeoDescription($data->seoDescription)
            ->save();

        return $brand;
    }
}
