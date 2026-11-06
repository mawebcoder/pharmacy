<?php

declare(strict_types=1);

namespace App\Modules\Identity\Exceptions;

use RuntimeException;

/**
 * A change to an admin account that would lock staff out of the panel.
 */
final class AdminUserChangeRefused extends RuntimeException
{
    public static function cannotDeactivateSelf(): self
    {
        return new self('نمی‌توانید حساب خودتان را غیرفعال کنید.');
    }

    public static function cannotRemoveOwnSuperAdminRole(): self
    {
        return new self('نمی‌توانید نقش مدیر ارشد را از خودتان بگیرید.');
    }

    public static function lastSuperAdmin(): self
    {
        return new self('دست‌کم یک مدیر ارشد فعال باید باقی بماند.');
    }
}
