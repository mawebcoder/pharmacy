<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Exceptions\AttributeChangeRefused;
use App\Modules\Catalog\Models\AttributeValue;

/**
 * Deletes a value of an attribute, unless products use it.
 */
final class DeleteAttributeValue
{
    /**
     * @throws AttributeChangeRefused
     */
    public function __invoke(AttributeValue $value): void
    {
        $products = $value->products()->count();

        if ($products > 0) {
            throw AttributeChangeRefused::valueInUse($value->getValueFa(), $products);
        }

        $value->delete();
    }
}
