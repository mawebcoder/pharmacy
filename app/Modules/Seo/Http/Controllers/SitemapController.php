<?php

declare(strict_types=1);

namespace App\Modules\Seo\Http\Controllers;

use App\Modules\Seo\Queries\SitemapUrls;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * /sitemap.xml lists one file per section (/sitemaps/{section}.xml) and the product files (/sitemaps/products-{n}.xml)
 * (roadmap S38). Each file is cached for an hour, so a new product or page appears within the hour.
 */
final class SitemapController
{
    private const int CACHE_SECONDS = 3600;

    public function index(SitemapUrls $urls): Response
    {
        $xml = Cache::remember('seo:sitemap:index', self::CACHE_SECONDS, function () use ($urls): string {
            $files = [
                ...array_map(fn (string $section): string => route('seo.sitemap.section', $section), SitemapUrls::SECTIONS),
                ...array_map(fn (int $file): string => route('seo.sitemap.products', $file), range(1, $urls->productFiles())),
            ];

            return '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
                .implode('', array_map(fn (string $loc): string => '<sitemap><loc>'.e($loc).'</loc></sitemap>', $files))
                .'</sitemapindex>';
        });

        return self::xml($xml);
    }

    public function section(SitemapUrls $urls, string $section): Response
    {
        abort_unless(in_array($section, SitemapUrls::SECTIONS, true), 404);

        return self::xml(Cache::remember('seo:sitemap:'.$section, self::CACHE_SECONDS, fn (): string => self::urlset($urls->section($section))));
    }

    public function products(SitemapUrls $urls, int $file): Response
    {
        abort_unless($file >= 1 && $file <= $urls->productFiles(), 404);

        return self::xml(Cache::remember('seo:sitemap:products-'.$file, self::CACHE_SECONDS, fn (): string => self::urlset($urls->products($file))));
    }

    /**
     * @param  list<array{loc: string, lastmod: ?Carbon}>  $entries
     */
    private static function urlset(array $entries): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            .implode('', array_map(fn (array $entry): string => '<url><loc>'.e($entry['loc']).'</loc>'
                .($entry['lastmod'] === null ? '' : '<lastmod>'.$entry['lastmod']->toAtomString().'</lastmod>')
                .'</url>', $entries))
            .'</urlset>';
    }

    private static function xml(string $xml): Response
    {
        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
