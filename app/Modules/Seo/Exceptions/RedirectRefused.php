<?php

declare(strict_types=1);

namespace App\Modules\Seo\Exceptions;

use RuntimeException;

/**
 * Why a redirect cannot be saved, in the admin's words.
 */
final class RedirectRefused extends RuntimeException
{
    public static function invalidAddress(): self
    {
        return new self('نشانی معتبر نیست؛ مسیری مثل /products/old-name یا نشانی کامل https:// بنویسید.');
    }

    public static function protectedPath(string $path): self
    {
        return new self("نشانی «{$path}» را نمی‌توان ریدایرکت کرد.");
    }

    public static function loop(): self
    {
        return new self('این ریدایرکت به همان نشانی برمی‌گردد و حلقه می‌سازد.');
    }
}
