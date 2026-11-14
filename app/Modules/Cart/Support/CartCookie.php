<?php

declare(strict_types=1);

namespace App\Modules\Cart\Support;

use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * The guest cart cookie (roadmap D13.3, owner 2026-11-14, S19): a 40-character random token, never the cart id,
 * encrypted and signed by the web middleware (EncryptCookies), HttpOnly, SameSite=Lax, Secure on HTTPS, 30 days.
 * The database keeps only its SHA-256 hash.
 */
final class CartCookie
{
    public const string NAME = 'cart_token';

    public const int LIFETIME_DAYS = 30;

    private const int TOKEN_LENGTH = 40;

    /**
     * Request attribute holding a token issued during this request, which the incoming cookie does not have yet.
     */
    private const string ISSUED_ATTRIBUTE = 'cart.issued_token';

    /**
     * The token from this request's cookie (already decrypted), or null when there is none or it is malformed.
     */
    public static function token(): ?string
    {
        $issued = request()->attributes->get(self::ISSUED_ATTRIBUTE);
        $token = is_string($issued) ? $issued : request()->cookie(self::NAME);

        return is_string($token) && preg_match('/^[A-Za-z0-9]{'.self::TOKEN_LENGTH.'}$/', $token) === 1 ? $token : null;
    }

    /**
     * Creates a token and queues the cookie on the response.
     */
    public static function issue(): string
    {
        $token = Str::random(self::TOKEN_LENGTH);
        request()->attributes->set(self::ISSUED_ATTRIBUTE, $token);
        Cookie::queue(Cookie::make(
            name: self::NAME,
            value: $token,
            minutes: self::LIFETIME_DAYS * 24 * 60,
            secure: request()->isSecure() || (bool) config('session.secure'),
            httpOnly: true,
            sameSite: 'lax',
        ));

        return $token;
    }

    public static function forget(): void
    {
        request()->attributes->remove(self::ISSUED_ATTRIBUTE);
        Cookie::queue(Cookie::forget(self::NAME));
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
