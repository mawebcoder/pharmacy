<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Exceptions;

use RuntimeException;

/**
 * A brand that products still use cannot be deleted; its products must move to another brand first.
 */
final class BrandHasProducts extends RuntimeException
{
    public static function cannotDelete(string $brandName): self
    {
        return new self("برند «{$brandName}» محصول دارد و حذف نمی‌شود. ابتدا محصولات آن را به برند دیگری منتقل کنید.");
    }
}
