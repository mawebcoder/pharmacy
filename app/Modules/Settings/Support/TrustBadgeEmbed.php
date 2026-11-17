<?php

declare(strict_types=1);

namespace App\Modules\Settings\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Cleans a trust badge's embed code (roadmap D16, S35), on save and again when shown. Only what e-Namad and
 * Samandehi hand out survives: a link and an image, both https on their own hosts, with referrerpolicy (e-Namad
 * checks the referring site, so links get rel="noopener", never noreferrer). Scripts, handlers, styles and any other
 * host are dropped; a code left without an image from those hosts is null.
 */
final class TrustBadgeEmbed
{
    /**
     * Hosts a seal may link to or load its image from.
     */
    public const array HOSTS = ['enamad.ir', 'trustseal.enamad.ir', 'samandehi.ir', 'logo.samandehi.ir'];

    public static function clean(?string $code): ?string
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        $code = (string) preg_replace('#<(script|style|iframe|object|embed|noscript|template|svg)\b[^>]*>.*?</\1\s*>#is', '', $code);
        $html = trim(self::sanitizer()->sanitize(strip_tags($code, ['a', 'img'])));

        return preg_match('/<img\b[^>]*\ssrc="https:/i', $html) === 1 ? $html : null;
    }

    private static function sanitizer(): HtmlSanitizer
    {
        return new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowElement('a', ['href', 'target', 'referrerpolicy'])
                ->allowElement('img', ['src', 'alt', 'referrerpolicy', 'id', 'code'])
                ->allowLinkSchemes(['https'])
                ->allowMediaSchemes(['https'])
                ->allowLinkHosts(self::HOSTS)
                ->allowMediaHosts(self::HOSTS)
                ->forceAttribute('a', 'rel', 'noopener')
                ->withMaxInputLength(5_000),
        );
    }
}
