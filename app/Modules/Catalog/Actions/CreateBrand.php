<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Data\BrandData;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Support\BrandSlug;

final class CreateBrand
{
    #[\NoDiscard]
    public function __invoke(BrandData $data): Brand
    {
        $brand = (new Brand)
            ->setNameFa($data->nameFa)
            ->setNameEn($data->nameEn)
            ->setSlug(BrandSlug::for($data->slug, $data->nameFa, $data->nameEn))
            ->setCountry($data->country)
            ->setDescription($data->description)
            ->setIsActive($data->isActive)
            ->setSeoTitle($data->seoTitle)
            ->setSeoDescription($data->seoDescription);

        $brand->save();

        return $brand;
    }
}
