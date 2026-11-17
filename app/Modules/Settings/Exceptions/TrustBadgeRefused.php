<?php

declare(strict_types=1);

namespace App\Modules\Settings\Exceptions;

use RuntimeException;

/**
 * A trust badge that would show nothing or something we do not trust; the message is shown to the admin.
 */
final class TrustBadgeRefused extends RuntimeException
{
    public static function embedNotAccepted(): self
    {
        return new self('این کد از نماد اعتماد الکترونیکی (enamad.ir) یا ساماندهی (samandehi.ir) نیست؛ کد را همان‌طور که سایت نماد داده کپی کنید.');
    }
}
