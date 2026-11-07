<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Exceptions\AttributeChangeRefused;
use App\Modules\Catalog\Models\Attribute;

/**
 * Moves an attribute to the trash, unless products use one of its values.
 */
final class DeleteAttribute
{
    /**
     * @throws AttributeChangeRefused
     */
    public function __invoke(Attribute $attribute): void
    {
        if ($attribute->isInUse()) {
            throw AttributeChangeRefused::attributeInUse($attribute->getNameFa());
        }

        $attribute->delete();
    }
}
