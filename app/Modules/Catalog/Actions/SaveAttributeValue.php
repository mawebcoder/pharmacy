<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Exceptions\AttributeChangeRefused;
use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Models\AttributeValue;
use App\Modules\Shared\Support\PersianNormalizer;

/**
 * Adds a value to an attribute (at the end of its list) or renames one. Values are unique within an attribute.
 */
final class SaveAttributeValue
{
    /**
     * @throws AttributeChangeRefused
     */
    #[\NoDiscard]
    public function __invoke(Attribute $attribute, string $valueFa, ?AttributeValue $value = null): AttributeValue
    {
        $valueFa = PersianNormalizer::letters(trim($valueFa));

        $duplicate = AttributeValue::query()
            ->where(AttributeValue::COLUMN_ATTRIBUTE_ID, $attribute->getId())
            ->where(AttributeValue::COLUMN_VALUE_FA, $valueFa)
            ->when($value instanceof AttributeValue, fn ($query) => $query->whereKeyNot($value?->getId()))
            ->exists();

        if ($duplicate) {
            throw AttributeChangeRefused::duplicateValue($valueFa);
        }

        $value ??= (new AttributeValue)
            ->setAttributeId($attribute->getId())
            ->setSort((int) $attribute->values()->max(AttributeValue::COLUMN_SORT) + 1);

        $value->setValueFa($valueFa)->save();

        return $value;
    }
}
