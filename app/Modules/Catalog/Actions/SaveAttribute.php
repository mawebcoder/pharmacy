<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Enums\AttributeType;
use App\Modules\Catalog\Exceptions\AttributeChangeRefused;
use App\Modules\Catalog\Models\Attribute;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates an attribute. Turning a multi-choice attribute into a single-choice one is refused while some
 * product has more than one of its values.
 */
final class SaveAttribute
{
    /**
     * @throws AttributeChangeRefused
     */
    #[\NoDiscard]
    public function __invoke(string $nameFa, string $code, AttributeType $type, bool $isFilterable, ?int $sort = null, ?Attribute $attribute = null): Attribute
    {
        if ($attribute instanceof Attribute && $attribute->isMultiple() && $type === AttributeType::Select && $this->hasProductWithSeveralValues($attribute)) {
            throw AttributeChangeRefused::cannotBecomeSingle($attribute->getNameFa());
        }

        $attribute ??= (new Attribute)->setSort((int) Attribute::query()->max(Attribute::COLUMN_SORT) + 1);

        $attribute
            ->setNameFa(trim($nameFa))
            ->setCode($code)
            ->setType($type)
            ->setIsFilterable($isFilterable);

        if ($sort !== null) {
            $attribute->setSort($sort);
        }

        $attribute->save();

        return $attribute;
    }

    private function hasProductWithSeveralValues(Attribute $attribute): bool
    {
        return DB::table('product_attribute_value')
            ->join('attribute_values', 'attribute_values.id', '=', 'product_attribute_value.attribute_value_id')
            ->where('attribute_values.attribute_id', $attribute->getId())
            ->select('product_attribute_value.product_id')
            ->groupBy('product_attribute_value.product_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
    }
}
