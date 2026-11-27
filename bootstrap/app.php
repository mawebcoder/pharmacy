<?php

declare(strict_types=1);

use App\Modules\Seo\Http\Middleware\RedirectOldPaths;
use App\Modules\Shared\Exceptions\BaseException;
use App\Modules\Shared\Http\Middleware\RedirectToHttps;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Carriers post their results here from outside; each webhook checks its own secret (S35).
        $middleware->validateCsrfTokens(except: ['webhooks/*']);

        // Production sits behind the ArvanCloud CDN, which reaches Nginx over plain HTTP: its X-Forwarded-For and
        // X-Forwarded-Proto give the visitor's IP and whether they came over HTTPS. Trusted from any address, so only
        // the CDN may reach the server's ports 80 and 443 (firewall, deploy/README.md).
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PORT,
        );
        // Appended, so it runs after the proxy headers are trusted (APP_FORCE_HTTPS).
        $middleware->append(RedirectToHttps::class);
        // Old addresses go to their new ones (S38), for every GET, also those matching no route.
        $middleware->append(RedirectOldPaths::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Customer-facing refusals render themselves as {message, code} (BaseException::render) and are not errors.
        $exceptions->dontReport(BaseException::class);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
