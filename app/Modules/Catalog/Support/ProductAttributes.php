<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Exceptions\AttributeChangeRefused;
use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Models\AttributeValue;
use Illuminate\Database\Eloquent\Collection;

/**
 * Checks the attribute values chosen for a product: each belongs to an attribute that is not deleted, and a
 * single-choice attribute has at most one value.
 */
final class ProductAttributes
{
    /**
     * @param  list<int>  $valueIds
     *
     * @throws AttributeChangeRefused
     */
    public static function assertValid(array $valueIds): void
    {
        if ($valueIds === []) {
            return;
        }

        /** @var Collection<int, AttributeValue> $values */
        $values = AttributeValue::query()->with('attribute')->whereKey($valueIds)->get();

        foreach ($values->groupBy(AttributeValue::COLUMN_ATTRIBUTE_ID) as $group) {
            $attribute = $group->first()?->getAttributeModel();

            if (! $attribute instanceof Attribute || $attribute->trashed()) {
                throw AttributeChangeRefused::valueOfDeletedAttribute();
            }

            if (! $attribute->isMultiple() && $group->count() > 1) {
                throw AttributeChangeRefused::tooManyValues($attribute->getNameFa());
            }
        }
    }
}
