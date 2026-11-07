<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Data\TagData;
use App\Modules\Catalog\Models\Tag;
use App\Modules\Catalog\Search\ProductSearchIndex;
use App\Modules\Catalog\Support\CatalogSlug;

/**
 * Creates or updates a tag. The slug comes from the English name, else the Persian name, and only changes when an
 * admin types a new one.
 */
final class SaveTag
{
    #[\NoDiscard]
    public function __invoke(TagData $data, ?Tag $tag = null): Tag
    {
        $slug = $tag instanceof Tag && ($data->slug === null || $data->slug === $tag->getSlug())
            ? $tag->getSlug()
            : CatalogSlug::for(Tag::class, $data->slug, $data->nameFa, $data->nameEn, $tag?->getId());

        $tag ??= new Tag;
        $tag->setNameFa($data->nameFa)
            ->setNameEn($data->nameEn)
            ->setSlug($slug)
            ->setType($data->type)
            ->setIsFeatured($data->isFeatured)
            ->setSort($data->sort)
            ->save();

        ProductSearchIndex::refreshWhere($tag->products()->getQuery());

        return $tag;
    }
}
