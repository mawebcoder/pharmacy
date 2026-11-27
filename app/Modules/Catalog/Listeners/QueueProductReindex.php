<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Listeners;

use App\Modules\Catalog\Events\AttributeValueSearchFieldsChanged;
use App\Modules\Catalog\Events\BrandSearchFieldsChanged;
use App\Modules\Catalog\Events\CategorySearchFieldsChanged;
use App\Modules\Catalog\Events\ProductsSearchFieldsChanged;
use App\Modules\Catalog\Events\SubcategorySearchFieldsChanged;
use App\Modules\Catalog\Events\TagSearchFieldsChanged;
use App\Modules\Catalog\Queries\SearchAffectedProducts;
use App\Modules\Catalog\Search\ProductSearchIndex;

/**
 * Turns any search event into the ids of the products whose documents copy what changed, and queues their rebuild in
 * bulk chunks (ProductSearchIndex::queue).
 */
final readonly class QueueProductReindex
{
    public function __construct(private SearchAffectedProducts $affected) {}

    public function handle(BrandSearchFieldsChanged|CategorySearchFieldsChanged|SubcategorySearchFieldsChanged|TagSearchFieldsChanged|AttributeValueSearchFieldsChanged|ProductsSearchFieldsChanged $event): void
    {
        ProductSearchIndex::queue(match (true) {
            $event instanceof BrandSearchFieldsChanged => $this->affected->ofBrand($event->brandId),
            $event instanceof CategorySearchFieldsChanged => $this->affected->ofCategory($event->categoryId),
            $event instanceof SubcategorySearchFieldsChanged => $this->affected->ofSubcategory($event->subcategoryId),
            $event instanceof TagSearchFieldsChanged => $this->affected->ofTag($event->tagId),
            $event instanceof AttributeValueSearchFieldsChanged => $this->affected->ofAttributeValue($event->attributeValueId),
            default => $event->productIds,
        });
    }
}
