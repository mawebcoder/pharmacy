<?php

declare(strict_types=1);

namespace App\Modules\Shared\Support;

/**
 * Text prepared for full-text search (roadmap D21.3), applied identically when indexing and when querying:
 * PersianNormalizer rules, every alef form as «ا», units written one way («۱۰۰۰ واحد», "1000IU" → "1000 iu"),
 * numbers split from letters, punctuation as spaces and Persian stopwords removed. English gets the same treatment
 * (owner, 2026-11-28): accents and in-word apostrophes dropped ("L'Oréal" → "loreal"), English stopwords removed, a
 * plural «s» dropped and British spellings written the American way.
 */
final class SearchText
{
    /**
     * Too common to help a search (roadmap D21.4.1), plus plural and verb endings that a half-space turned into words
     * («کرم‌های» → «کرم های»), so «کرم های مرطوب» finds «کرم مرطوب» (owner's normalization guide, 2026-11-28). Never «می»
     * (it would leave «روم»), «نه» or «بدون» («بدون شکر» must not find «شکر»).
     */
    public const array STOPWORDS = [
        'از', 'به', 'با', 'در', 'برای', 'و', 'یا', 'که', 'این', 'آن', 'ان', 'را', 'تا', 'بر',
        'ها', 'های', 'هایی', 'است', 'بود', 'شد', 'شده', 'هم', 'نیز', 'هر', 'اما', 'اگر', 'چون',
        // English. Never "a" or "e" (vitamin A, vitamin E), "no", "free" or "without" ("sugar free").
        'the', 'of', 'and', 'for', 'with', 'by', 'an', 'at', 'from',
    ];

    /**
     * Words with more than one accepted spelling, rewritten to one form on both sides (keys after the letter rules:
     * «ئ» is already «ی», «أ» already «ا»). Exact and free, unlike a synonym; grow it from «بینش جستجو».
     */
    public const array VARIANTS = [
        'اطاق' => 'اتاق', 'طهران' => 'تهران', 'هییت' => 'هیات', 'مسیله' => 'مساله', 'مسیول' => 'مسوول',
        'بلیط' => 'بلیت', 'باطری' => 'باتری', 'اطو' => 'اتو', 'غلطک' => 'غلتک', 'توفان' => 'طوفان',
        'ذغال' => 'زغال', 'امپراطور' => 'امپراتور', 'پوماد' => 'پماد',
        // British → American, keys in the singular (the plural «s» is dropped first).
        'moisturiser' => 'moisturizer', 'moisturising' => 'moisturizing', 'moisturise' => 'moisturize',
        'colour' => 'color', 'coloured' => 'colored', 'flavour' => 'flavor', 'flavoured' => 'flavored', 'odour' => 'odor',
        'fibre' => 'fiber', 'centre' => 'center', 'grey' => 'gray', 'ageing' => 'aging', 'aluminium' => 'aluminum',
        'sulphate' => 'sulfate', 'sulphur' => 'sulfur', 'paediatric' => 'pediatric', 'anaemia' => 'anemia',
        'haemorrhoid' => 'hemorrhoid', 'oestrogen' => 'estrogen', 'foetal' => 'fetal', 'diarrhoea' => 'diarrhea',
    ];

    /**
     * Letters that sound the same (owner's normalization guide): س ص ث, ز ذ ض ظ, ت ط, ه ح, ق غ, ا ع.
     */
    private const array PHONETIC = ['ص' => 'س', 'ث' => 'س', 'ذ' => 'ز', 'ض' => 'ز', 'ظ' => 'ز', 'ط' => 'ت', 'ح' => 'ه', 'غ' => 'ق', 'ع' => 'ا'];

    /**
     * English letters that sound the same ("c" before e, i or y is already «s»): "cinere" and "sinere" both give "sinere".
     */
    private const array PHONETIC_EN = ['ph' => 'f', 'ck' => 'k', 'c' => 'k', 'q' => 'k', 'x' => 'ks', 'z' => 's', 'y' => 'i'];

    /**
     * The same keys on the English and Persian (standard ISIRI) keyboard layouts.
     */
    private const string KEYS_EN = "qwertyuiop[]asdfghjkl;'zxcvbnm,";

    private const string KEYS_FA = 'ضصثقفغعهخحجچشسیبلاتنمکگظطزرذدپو';

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
        // Accents off ("é" → "e"); this also turns «آ» into «ا».
        $text = (string) preg_replace('/\p{Mn}+/u', '', \Normalizer::normalize($text, \Normalizer::FORM_D) ?: $text);
        $text = \Normalizer::normalize($text, \Normalizer::FORM_C) ?: $text;
        $text = strtr($text, ['آ' => 'ا']);
        $text = (string) preg_replace("/(?<=\p{L})['’ʼ](?=\p{L})/u", '', $text);
        $text = (string) preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text);
        $text = (string) preg_replace('/(\p{N})(\p{L})/u', '$1 $2', $text);
        $text = (string) preg_replace('/(\p{L})(\p{N})/u', '$1 $2', $text);
        $text = (string) preg_replace('/\s+/u', ' ', trim($text));

        foreach (self::UNITS as $spelling => $unit) {
            $text = (string) preg_replace('/(?<=\d) '.preg_quote($spelling, '/').'(?=\s|$)/u', ' '.$unit, $text);
        }

        $words = array_filter(explode(' ', $text), fn (string $word): bool => $word !== '' && ! in_array($word, self::STOPWORDS, true));

        return implode(' ', array_map(fn (string $word): string => self::VARIANTS[self::singular($word)] ?? self::singular($word), $words));
    }

    /**
     * An English word without its plural ending: "creams" → "cream", "berries" → "berry". Short words and the endings
     * "ss", "us" and "is" stay ("glass", "plus", "iris"). Rough, but the same on both sides.
     */
    private static function singular(string $word): string
    {
        if (preg_match('/^[a-z]{4,}$/', $word) !== 1 || preg_match('/(ss|us|is)$/', $word) === 1) {
            return $word;
        }

        if (str_ends_with($word, 'ies') && strlen($word) > 4) {
            return substr($word, 0, -3).'y';
        }

        return str_ends_with($word, 's') ? substr($word, 0, -1) : $word;
    }

    /**
     * The sound key rules on normalised text: Persian look-alike sounds, then English ones, with doubled Latin letters
     * written once.
     */
    private static function sound(string $normalized): string
    {
        $text = strtr($normalized, self::PHONETIC);
        $text = strtr((string) preg_replace('/c(?=[eiy])/', 's', $text), self::PHONETIC_EN);

        return (string) preg_replace('/([a-z])\1+/', '$1', $text);
    }

    /**
     * The sound key of a text, for misspellings by ear: «ضد آفتاب» and «زد افتاب» both give «زد افتاب», "cyneere"
     * and "cinere" both "sinere".
     */
    public static function phonetic(string $text): string
    {
        return self::sound(self::normalize($text));
    }

    /**
     * The sound key as indexed, with joined pairs like forIndex(): «ضد آفتاب» → «زد افتاب زدافتاب».
     */
    public static function phoneticForIndex(string $text): string
    {
        return self::sound(self::forIndex($text));
    }

    /**
     * A Persian sound skeleton of Persian typed in Latin letters (Finglish), or null when the text is not Latin letters:
     * "kerem" → «کرم», "ghors" → «قرس», "shampoo" → «شامپو». Written Persian drops short vowels, so «e» and «o» inside a
     * word are dropped (a final «e» is «ه»); «a» is «ا», «i» / «ee» «ی», «u» / «oo» «و». Rough by nature: meant for the
     * phonetic field, where typo tolerance absorbs the rest (owner, 2026-11-28).
     */
    public static function finglish(string $text): ?string
    {
        $text = mb_strtolower(trim($text));

        if (preg_match('/^[a-z\s]+$/', $text) !== 1) {
            return null;
        }

        $words = array_map(function (string $word): string {
            $word = strtr($word, ['sh' => 'ش', 'ch' => 'چ', 'kh' => 'خ', 'gh' => 'ق', 'zh' => 'ژ', 'ee' => 'ی', 'oo' => 'و', 'ou' => 'و', 'ph' => 'ف']);
            $word = (string) preg_replace('/^[eo]/u', 'ا', $word);
            $word = (string) preg_replace('/e$/u', 'ه', $word);
            $word = str_replace(['e', 'o'], '', $word);

            return strtr($word, [
                'a' => 'ا', 'b' => 'ب', 'c' => 'س', 'd' => 'د', 'f' => 'ف', 'g' => 'گ', 'h' => 'ه', 'i' => 'ی', 'j' => 'ج',
                'k' => 'ک', 'l' => 'ل', 'm' => 'م', 'n' => 'ن', 'p' => 'پ', 'q' => 'ق', 'r' => 'ر', 's' => 'س', 't' => 'ت',
                'u' => 'و', 'v' => 'و', 'w' => 'و', 'x' => 'کس', 'y' => 'ی', 'z' => 'ز',
            ]);
        }, array_filter(explode(' ', $text)));

        return implode(' ', $words);
    }

    /**
     * The text typed on the other keyboard layout, or null when it is not all letters of one layout: "sghl" → «سلام»,
     * «لخخلمث» → "google".
     */
    public static function otherLayout(string $text): ?string
    {
        $text = mb_strtolower(trim($text));
        $latin = mb_str_split(self::KEYS_EN);
        $persian = mb_str_split(self::KEYS_FA);

        if (preg_match('/^[a-z;\'\[\],\s]+$/u', $text) === 1) {
            return strtr($text, array_combine($latin, $persian));
        }

        if (preg_match('/^[\x{0600}-\x{06FF}\s]+$/u', $text) === 1) {
            return strtr($text, array_combine($persian, $latin));
        }

        return null;
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
