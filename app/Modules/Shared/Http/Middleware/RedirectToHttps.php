<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends a plain-HTTP request to the same URL on HTTPS when config('app.force_https') is on (production behind the
 * CDN). Runs after the trusted-proxy middleware, so a request that reached the CDN over HTTPS counts as secure.
 * GET and HEAD get a 301; other methods a 308, which keeps the method and body (a carrier's webhook POST).
 */
final class RedirectToHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('app.force_https') || $request->secure()) {
            return $next($request);
        }

        $status = $request->isMethodSafe() ? Response::HTTP_MOVED_PERMANENTLY : Response::HTTP_PERMANENTLY_REDIRECT;

        return redirect()->secure($request->getRequestUri(), $status);
    }
}
