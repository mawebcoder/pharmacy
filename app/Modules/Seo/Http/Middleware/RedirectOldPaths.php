<?php

declare(strict_types=1);

namespace App\Modules\Seo\Http\Middleware;

use App\Modules\Seo\Models\SeoRedirect;
use App\Modules\Seo\Support\SeoPath;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends a GET or HEAD for an old address on to its new one (roadmap S38), with the query string. Runs for every
 * request, before routing, so old addresses that match no route are caught too; the map of every redirect is cached
 * (cleared when one changes), so a request costs one cache read.
 */
final class RedirectOldPaths
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        $path = SeoPath::normalize($request->getPathInfo());
        $redirect = $path === null ? null : (self::map()[$path] ?? null);

        if ($redirect === null) {
            return $next($request);
        }

        SeoRedirect::query()->whereKey($redirect['id'])->increment(SeoRedirect::COLUMN_HITS, 1, [SeoRedirect::COLUMN_LAST_HIT_AT => now()]);
        $query = $request->getQueryString();

        return redirect()->to($redirect['to'].($query === null ? '' : (str_contains($redirect['to'], '?') ? '&' : '?').$query), $redirect['status']);
    }

    /**
     * @return array<string, array{id: int, to: string, status: int}>
     */
    private static function map(): array
    {
        /** @var array<string, array{id: int, to: string, status: int}> */
        return Cache::rememberForever(SeoRedirect::CACHE_KEY, fn (): array => SeoRedirect::query()
            ->get([SeoRedirect::COLUMN_ID, SeoRedirect::COLUMN_FROM_PATH, SeoRedirect::COLUMN_TO_PATH, SeoRedirect::COLUMN_STATUS_CODE])
            ->mapWithKeys(fn (SeoRedirect $redirect): array => [$redirect->getFromPath() => ['id' => $redirect->getId(), 'to' => $redirect->getToPath(), 'status' => $redirect->getStatusCode()]])
            ->all());
    }
}
