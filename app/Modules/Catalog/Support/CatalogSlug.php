<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Support;

use App\Modules\Shared\Support\PersianSlug;
use Illuminate\Database\Eloquent\Model;

/**
 * Catalog URLs (brands, categories): from the English name ("Eucerin" → "eucerin"), else the Persian name, unique
 * among all rows of the model including deleted ones (their slugs stay reserved until they are gone for good).
 */
final class CatalogSlug
{
    /**
     * @param  class-string<Model>  $modelClass  a model using SoftDeletes with a "slug" column
     */
    public static function for(string $modelClass, ?string $requested, string $nameFa, ?string $nameEn, ?int $ignoreId = null): string
    {
        $source = $requested ?? (filled($nameEn) ? $nameEn : $nameFa);

        return PersianSlug::unique($source, fn(string $slug): bool => $modelClass::withTrashed() // @phpstan-ignore staticMethod.notFound (soft-deleting models only)
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists());
    }
}
