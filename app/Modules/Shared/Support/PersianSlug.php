<?php

declare(strict_types=1);

namespace App\Modules\Shared\Support;

use Closure;

/**
 * URL slugs that keep Persian letters (Laravel's Str::slug would transliterate them):
 * "ویتامین D3 ۱۰۰۰ واحد" → "ویتامین-d3-1000-واحد".
 */
final class PersianSlug
{
    public static function make(string $text, string $separator = '-'): string
    {
        $text = PersianNormalizer::normalize($text);
        $text = (string) preg_replace('/[^\p{L}\p{N}]+/u', $separator, $text);

        return trim($text, $separator);
    }

    /**
     * A slug not yet taken: adds "-2", "-3", … while $isTaken returns true.
     *
     * @param  Closure(string): bool  $isTaken
     */
    public static function unique(string $text, Closure $isTaken, string $separator = '-'): string
    {
        $base = self::make($text, $separator);
        $slug = $base;
        $suffix = 2;

        while ($isTaken($slug)) {
            $slug = $base.$separator.$suffix++;
        }

        return $slug;
    }
}
