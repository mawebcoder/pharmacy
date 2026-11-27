<?php

declare(strict_types=1);

namespace App\Modules\Seo\Support;

use App\Modules\Settings\Queries\StoreSettings;

/**
 * Shared schema.org JSON-LD blocks (roadmap S38): BreadcrumbList, and the store as OnlineStore with its WebSite search
 * box. The Product block is Catalog's (Catalog\Support\ProductSeo).
 */
final class StructuredData
{
    /**
     * @param  list<array{name: string, url: string}>  $trail  from the home page down to the current page
     * @return array<string, mixed>
     */
    public static function breadcrumbs(array $trail): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn (array $item, int $index): array => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'item' => $item['url'],
            ], $trail, array_keys($trail)),
        ];
    }

    /**
     * The store and its search box, for the home page.
     *
     * @return list<array<string, mixed>>
     */
    public static function store(StoreSettings $settings): array
    {
        $name = (string) config('app.display_name');

        return [
            array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'OnlineStore',
                'name' => $name,
                'url' => url('/'),
                'description' => SeoDefaults::DESCRIPTION,
                'telephone' => $settings->phone(),
                'email' => $settings->email(),
                'address' => $settings->address() === null ? null : ['@type' => 'PostalAddress', 'streetAddress' => $settings->address(), 'addressCountry' => 'IR'],
                'sameAs' => array_map(fn (array $link): string => $link['url'], $settings->socialLinks()) ?: null,
            ], fn (mixed $value): bool => $value !== null && $value !== ''),
            [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => $name,
                'url' => url('/'),
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => ['@type' => 'EntryPoint', 'urlTemplate' => url('/search').'?q={search_term_string}'],
                    'query-input' => 'required name=search_term_string',
                ],
            ],
        ];
    }

    public static function absolute(?string $url): ?string
    {
        return $url === null || $url === '' ? null : url($url);
    }
}
