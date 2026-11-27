<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Shared\Support\PersianNormalizer;

/**
 * Reads the catalog import's cells (owner, 2026-11-27): the product type by its Persian label or value, «بله»/«خیر»,
 * numbers with Persian digits and separators, the image URLs of one cell, and product names for comparing.
 */
final class CatalogImportValues
{
    public const int MAX_IMAGES = 10;

    public static function productType(mixed $value): ?ProductType
    {
        $value = PersianNormalizer::letters(trim((string) $value));

        foreach (ProductType::cases() as $type) {
            if ($value === $type->value || $value === PersianNormalizer::letters($type->label())) {
                return $type;
            }
        }

        return null;
    }

    /**
     * «بله»/«خیر» (also yes/no, 1/0, true/false); null for blank or anything else.
     */
    public static function bool(mixed $value): ?bool
    {
        $value = mb_strtolower(PersianNormalizer::digits(trim((string) $value)));

        return match (true) {
            in_array($value, ['بله', 'آره', 'هست', 'yes', 'y', '1', 'true'], true) => true,
            in_array($value, ['خیر', 'نه', 'نیست', 'no', 'n', '0', 'false'], true) => false,
            default => null,
        };
    }

    /**
     * Persian digits and thousands separators removed: «۴۸۵٬۰۰۰» → «485000»; blank stays null.
     */
    public static function number(mixed $value): ?string
    {
        $value = str_replace([',', '٬', '،', ' '], '', PersianNormalizer::digits(trim((string) $value)));

        return $value === '' ? null : $value;
    }

    /**
     * The image URLs of a cell, separated by commas (Latin or Persian), semicolons or line breaks.
     *
     * @return list<string>
     */
    public static function imageUrls(mixed $value): array
    {
        $urls = preg_split('/[,،;\s]+/u', trim((string) $value)) ?: [];

        return array_values(array_unique(array_filter(array_map(trim(...), $urls), fn (string $url): bool => $url !== '')));
    }

    /**
     * A product name for comparing: Arabic «ي»/«ك», extra spaces and Latin case do not matter.
     */
    public static function nameKey(string $name): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', PersianNormalizer::letters(trim($name))));
    }
}
