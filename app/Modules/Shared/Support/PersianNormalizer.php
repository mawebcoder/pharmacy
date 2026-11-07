<?php

declare(strict_types=1);

namespace App\Modules\Shared\Support;

/**
 * Makes Persian text comparable (roadmap D21.3): the same rules apply when storing search text, when searching,
 * when matching names from other systems (Tipax cities) and when normalising form input.
 */
final class PersianNormalizer
{
    private const string ZWNJ = "\u{200C}";

    /**
     * Arabic letters that look like Persian ones but are different characters.
     */
    private const array LETTERS = [
        'ي' => 'ی', 'ى' => 'ی', 'ئ' => 'ی',
        'ك' => 'ک',
        'ة' => 'ه', 'ۀ' => 'ه',
        'أ' => 'ا', 'إ' => 'ا', 'ٱ' => 'ا',
        'ؤ' => 'و',
    ];

    /**
     * Arabic diacritics (harakat, tanwin, shadda, sukun, superscript alef) and the tatweel stretch mark.
     */
    private const string DIACRITICS = '/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u';

    /**
     * Persian and Arabic-Indic digits to Latin: "۰۹۱۲" → "0912".
     */
    public static function digits(string $text): string
    {
        return PersianDigits::toLatin($text);
    }

    /**
     * Arabic look-alike letters to Persian, keeping "آ" for display: "ويتامين" → "ویتامین".
     */
    public static function letters(string $text): string
    {
        return strtr($text, self::LETTERS);
    }

    /**
     * The comparable form: Persian letters, Latin digits, no diacritics or stretch marks, half-spaces as spaces,
     * lowercase Latin, single spaces, trimmed. "  ويتامين  D3 كودك " → "ویتامین d3 کودک".
     */
    public static function normalize(string $text): string
    {
        $text = self::letters(self::digits($text));
        $text = (string) preg_replace(self::DIACRITICS, '', $text);
        $text = str_replace(self::ZWNJ, ' ', $text);
        $text = (string) preg_replace('/\s+/u', ' ', $text);

        return mb_strtolower(trim($text));
    }

    /**
     * Search text that matches with or without spaces between parts: "ضد آفتاب" → "ضد آفتاب ضدآفتاب".
     */
    public static function forIndex(string $text): string
    {
        $normalized = self::normalize($text);
        $joined = str_replace(' ', '', $normalized);

        return $joined === $normalized || ! str_contains($normalized, ' ') ? $normalized : "{$normalized} {$joined}";
    }

    /**
     * Whether two names are the same once normalised, ignoring spaces: "بندر عباس" equals "بندرعباس".
     */
    public static function equals(string $first, string $second): bool
    {
        return str_replace(' ', '', self::normalize($first)) === str_replace(' ', '', self::normalize($second));
    }
}
