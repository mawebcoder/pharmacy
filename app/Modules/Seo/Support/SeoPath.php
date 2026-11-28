<?php

declare(strict_types=1);

namespace App\Modules\Seo\Support;

/**
 * One way to write a site address for redirects (roadmap S38): the path only (a full https://hutana.ir/… address is cut
 * to its path), decoded (Persian slugs as letters), starting with «/», without a trailing «/» or query string.
 */
final class SeoPath
{
    public static function normalize(string $address): ?string
    {
        $path = (string) (parse_url(trim($address), PHP_URL_PATH) ?? '');
        $path = '/'.trim((string) preg_replace('#/+#', '/', rawurldecode($path)), '/');

        return $path === '/' && trim($address) === '' ? null : $path;
    }

    /**
     * A redirect target: a path on this site, or a full https:// address elsewhere.
     */
    public static function target(string $address): ?string
    {
        $address = trim($address);
        $host = parse_url($address, PHP_URL_HOST);

        if (is_string($host) && $host !== parse_url((string) config('app.url'), PHP_URL_HOST)) {
            return str_starts_with($address, 'https://') ? $address : null;
        }

        return self::normalize($address);
    }
}
