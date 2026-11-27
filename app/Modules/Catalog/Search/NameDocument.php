<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Search;

use App\Modules\Shared\Support\SearchText;
use Carbon\CarbonInterface;

/**
 * The Meilisearch document of a brand or a category (owner, 2026-11-15: each in its own index, so the search panel
 * finds them with the same typo tolerance and synonyms as products). Names are normalised with SearchText, the same
 * way queries are. Every item that is not deleted is indexed (owner, 2026-11-27); the storefront filters on is_active,
 * and whether a subcategory's category is active is checked on reading, against the cached CategoryTree.
 */
final class NameDocument
{
    /**
     * The slugs and update time are for sitemap.xml, read from the index (owner, 2026-11-27); a subcategory's address
     * also needs its category's slug.
     *
     * @return array{id: int, name_fa: string, name_en: string, is_active: bool, slug: string, category_slug: string|null, updated_at: int}
     */
    public static function for(int $id, string $nameFa, ?string $nameEn, bool $isActive, string $slug, ?CarbonInterface $updatedAt, ?string $categorySlug = null): array
    {
        return [
            'id' => $id,
            'name_fa' => SearchText::forIndex($nameFa),
            'name_en' => SearchText::normalize((string) $nameEn),
            'is_active' => $isActive,
            'slug' => $slug,
            'category_slug' => $categorySlug,
            'updated_at' => $updatedAt?->getTimestamp() ?? 0,
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
