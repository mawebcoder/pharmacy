<?php

declare(strict_types=1);

namespace App\Modules\Seo\Queries;

use App\Modules\Catalog\Queries\SitemapAddresses;
use App\Modules\Content\Queries\PageAddresses;
use Illuminate\Support\Carbon;

/**
 * The addresses in sitemap.xml (roadmap S38), by section: the fixed pages and the static pages (Content), and the
 * catalog's categories, brands, tags, collections and product files (Catalog\Queries\SitemapAddresses). A new section is
 * one more name in SECTIONS and one more case below.
 */
final readonly class SitemapUrls
{
    public const array SECTIONS = ['pages', ...SitemapAddresses::SECTIONS];

    public function __construct(private SitemapAddresses $catalog, private PageAddresses $pages) {}

    public function productFiles(): int
    {
        return $this->catalog->productFiles();
    }

    /**
     * @return list<array{loc: string, lastmod: ?Carbon}>
     */
    public function products(int $file): array
    {
        return $this->catalog->products($file);
    }

    /**
     * @return list<array{loc: string, lastmod: ?Carbon}>
     */
    public function section(string $section): array
    {
        if ($section !== 'pages') {
            return $this->catalog->section($section);
        }

        $fixed = array_map(
            fn (string $route): array => ['loc' => route($route), 'lastmod' => null],
            ['home', 'products.index', 'brands.index', 'categories.index', 'collections.index', 'deals', 'faq', 'contact', 'pharmacist'],
        );

        return [...$fixed, ...$this->pages->active()];
    }
}
