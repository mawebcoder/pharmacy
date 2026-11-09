<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Exceptions;

use RuntimeException;

/**
 * A collection change that would break a rule; the message is shown to the admin.
 */
final class CollectionChangeRefused extends RuntimeException
{
    public static function systemCollection(string $name): self
    {
        return new self("«{$name}» کالکشن سیستمی است و حذف نمی‌شود؛ می‌توانید محصولاتش را تغییر دهید.");
    }
}
