<?php

declare(strict_types=1);

namespace App\Modules\Seo\Support;

/**
 * Plain, short text for meta descriptions from what a page has: the admin's SEO description, else a short or rich
 * description with its HTML removed, cut at a word near 155 characters (what search results show).
 */
final class SeoText
{
    public const int DESCRIPTION_LENGTH = 155;

    public static function description(?string ...$candidates): ?string
    {
        foreach ($candidates as $candidate) {
            $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $candidate), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

            if ($text !== '') {
                return self::cut($text);
            }
        }

        return null;
    }

    private static function cut(string $text): string
    {
        if (mb_strlen($text) <= self::DESCRIPTION_LENGTH) {
            return $text;
        }

        $cut = mb_substr($text, 0, self::DESCRIPTION_LENGTH);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space === false ? $cut : mb_substr($cut, 0, $space), ' ،,.؛:').'…';
    }
}
