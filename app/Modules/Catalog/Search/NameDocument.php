<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Search;

use App\Modules\Shared\Support\SearchText;

/**
 * The Meilisearch document of a brand or a category (owner, 2026-11-15: each in its own index, so the search panel
 * finds them with the same typo tolerance and synonyms as products). Names are normalised with SearchText, the same
 * way queries are. Every item that is not deleted is indexed (owner, 2026-11-27); the storefront filters on is_active,
 * and whether a subcategory's category is active is checked on reading, against the cached CategoryTree.
 */
final class NameDocument
{
    /**
     * @return array{id: int, name_fa: string, name_en: string, is_active: bool}
     */
    public static function for(int $id, string $nameFa, ?string $nameEn, bool $isActive): array
    {
        return [
            'id' => $id,
            'name_fa' => SearchText::forIndex($nameFa),
            'name_en' => SearchText::normalize((string) $nameEn),
            'is_active' => $isActive,
        ];
    }

    /**
     * Meilisearch index settings (applied with `php artisan scout:sync-index-settings`), typo rules as for products.
     *
     * @return array<string, mixed>
     */
    public static function indexSettings(): array
    {
        return [
            'searchableAttributes' => ['name_fa', 'name_en'],
            'filterableAttributes' => ['is_active'],
            'typoTolerance' => [
                'minWordSizeForTypos' => ['oneTypo' => 4, 'twoTypos' => 8],
            ],
        ];
    }
}
