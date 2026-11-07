<?php

declare(strict_types=1);

namespace App\Modules\Shared\Support;

/**
 * Text prepared for full-text search (roadmap D21.3), applied identically when indexing and when querying:
 * PersianNormalizer rules, every alef form as «ا», units written one way («۱۰۰۰ واحد», "1000IU" → "1000 iu"),
 * numbers split from letters, punctuation as spaces and Persian stopwords removed.
 */
final class SearchText
{
    /**
     * Too common to help a search (roadmap D21.4.1).
     */
    public const array STOPWORDS = ['از', 'به', 'با', 'در', 'برای', 'و', 'یا', 'که', 'این', 'آن', 'ان', 'را', 'تا', 'بر'];

    /**
     * Unit spellings → one form. Longer spellings first so "میلی گرم" is not read as "گرم".
     */
    private const array UNITS = [
        'بین المللی' => 'iu', 'واحد' => 'iu', 'iu' => 'iu',
        'میلی گرم' => 'mg', 'میلیگرم' => 'mg', 'mg' => 'mg',
        'میکرو گرم' => 'mcg', 'میکروگرم' => 'mcg', 'mcg' => 'mcg', 'µg' => 'mcg',
        'میلی لیتر' => 'ml', 'میلیلیتر' => 'ml', 'ml' => 'ml',
        'گرم' => 'g', 'سی سی' => 'ml', 'cc' => 'ml',
    ];

    /**
     * The searchable form of a text: "ويتامين D3 ۱۰۰۰ واحد" → "ویتامین d 3 1000 iu".
     */
    public static function normalize(string $text): string
    {
        $text = PersianNormalizer::normalize($text);
        $text = strtr($text, ['آ' => 'ا']);
        $text = (string) preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text);
        $text = (string) preg_replace('/(\p{N})(\p{L})/u', '$1 $2', $text);
        $text = (string) preg_replace('/(\p{L})(\p{N})/u', '$1 $2', $text);
        $text = (string) preg_replace('/\s+/u', ' ', trim($text));

        foreach (self::UNITS as $spelling => $unit) {
            $text = (string) preg_replace('/(?<=\d) '.preg_quote($spelling, '/').'(?=\s|$)/u', ' '.$unit, $text);
        }

        $words = array_filter(explode(' ', $text), fn (string $word): bool => $word !== '' && ! in_array($word, self::STOPWORDS, true));

        return implode(' ', $words);
    }

    /**
     * The words of a query, normalised (for building boolean full-text queries).
     *
     * @return list<string>
     */
    public static function words(string $text): array
    {
        $normalized = self::normalize($text);

        return $normalized === '' ? [] : array_values(array_unique(explode(' ', $normalized)));
    }

    /**
     * Indexed text that also matches when parts are written together: "ضد افتاب" → "ضد افتاب ضدافتاب".
     */
    public static function forIndex(string $text): string
    {
        $normalized = self::normalize($text);
        $words = explode(' ', $normalized);
        $joined = [];

        for ($i = 0, $count = count($words); $i + 1 < $count; $i++) {
            if (! is_numeric($words[$i]) && ! is_numeric($words[$i + 1])) {
                $joined[] = $words[$i].$words[$i + 1];
            }
        }

        return trim($normalized.' '.implode(' ', array_unique($joined)));
    }
}
