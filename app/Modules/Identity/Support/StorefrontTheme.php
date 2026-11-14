<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use App\Modules\Identity\Enums\ThemePreference;
use App\Modules\Identity\Models\Customer;
use Illuminate\Support\Facades\Cookie;

/**
 * The storefront theme for this request (owner, 2026-11-14): a signed-in customer's choice from their account,
 * otherwise the header switch's cookie, otherwise the device setting. The cookie is encrypted by the web middleware,
 * HttpOnly, SameSite=Lax, one year.
 */
final class StorefrontTheme
{
    public const string COOKIE = 'theme';

    public const int LIFETIME_DAYS = 365;

    public static function current(): ThemePreference
    {
        $customer = auth('customer')->user();

        if ($customer instanceof Customer) {
            return $customer->getTheme();
        }

        $cookie = request()->cookie(self::COOKIE);

        return (is_string($cookie) ? ThemePreference::tryFrom($cookie) : null) ?? ThemePreference::System;
    }

    public static function remember(ThemePreference $theme): void
    {
        Cookie::queue(Cookie::make(
            name: self::COOKIE,
            value: $theme->value,
            minutes: self::LIFETIME_DAYS * 24 * 60,
            secure: request()->isSecure() || (bool) config('session.secure'),
            httpOnly: true,
            sameSite: 'lax',
        ));
    }
}
