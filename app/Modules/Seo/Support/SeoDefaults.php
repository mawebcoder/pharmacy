<?php

declare(strict_types=1);

namespace App\Modules\Seo\Support;

use App\Modules\Seo\Data\SeoMeta;
use Illuminate\Http\Request;

/**
 * Site-wide SEO rules (roadmap S38): the default description, which pages stay out of search results, and the
 * canonical address. A list with any query parameter but `page` (filters, sort, search text) is a variant of the clean
 * list: noindex, follow, canonical to the clean address. `?page=N` lists (the crawlable pages behind the infinite
 * scroll) keep their page in the canonical and stay indexable.
 */
final class SeoDefaults
{
    public const string DESCRIPTION = 'هوتانا؛ خرید آنلاین محصولات آرایشی، بهداشتی، مکمل و تجهیزات پزشکی از شرکت‌های پخش معتبر، با ارسال به سراسر ایران.';

    /** Pages for one shopper, or of no use in search results. */
    public const array NOINDEX_ROUTES = ['account*', 'cart*', 'checkout*', 'login', 'orders.*', 'payments.*', 'search*', 'dev.*'];

    public static function isNoindex(Request $request, ?SeoMeta $seo): bool
    {
        return ($seo !== null && $seo->noindex)
            || $request->routeIs(self::NOINDEX_ROUTES)
            || array_diff(array_keys($request->query()), ['page']) !== [];
    }

    public static function canonical(Request $request): string
    {
        $page = (int) $request->query('page', '1');

        return $request->url().($page > 1 ? '?page='.$page : '');
    }
}
