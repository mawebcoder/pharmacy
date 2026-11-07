<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Shared\Support\PersianSlug;

/**
 * Brand URLs: from the English name ("Eurho Vital" → "eurho-vital"), else the Persian name, unique among all
 * brands including deleted ones (their slugs stay reserved until they are gone for good).
 */
final class BrandSlug
{
    public static function for(?string $requested, string $nameFa, ?string $nameEn, ?int $ignoreBrandId = null): string
    {
        $source = $requested ?? (filled($nameEn) ? $nameEn : $nameFa);

        return PersianSlug::unique($source, fn (string $slug): bool => Brand::withTrashed()
            ->where(Brand::COLUMN_SLUG, $slug)
            ->when($ignoreBrandId !== null, fn ($query) => $query->whereKeyNot($ignoreBrandId))
            ->exists());
    }
}
