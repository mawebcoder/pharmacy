<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Data\CollectionData;
use App\Modules\Catalog\Models\ProductCollection;
use App\Modules\Catalog\Support\CatalogSlug;

/**
 * Creates or updates a collection. The slug of the system collection («محصولات محبوب») never changes, because the
 * empty search box finds it by slug.
 */
final class SaveCollection
{
    #[\NoDiscard]
    public function __invoke(CollectionData $data, ?ProductCollection $collection = null): ProductCollection
    {
        $keepsSlug = $collection instanceof ProductCollection
            && ($collection->isSystem() || $data->slug === null || $data->slug === $collection->getSlug());
        $slug = $keepsSlug
            ? $collection->getSlug()
            : CatalogSlug::for(ProductCollection::class, $data->slug, $data->nameFa, $data->nameEn, $collection?->getId());

        $collection ??= new ProductCollection;
        $collection->setNameFa($data->nameFa)
            ->setNameEn($data->nameEn)
            ->setSlug($slug)
            ->setDescription($data->description)
            ->setIsActive($data->isActive)
            ->setStartsAt($data->startsAt)
            ->setEndsAt($data->endsAt)
            ->setSort($data->sort)
            ->save();

        return $collection;
    }
}
