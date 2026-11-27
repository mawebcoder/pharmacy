<?php

declare(strict_types=1);

namespace App\Modules\Seo\Http\Controllers;

use Illuminate\Http\Response;

/**
 * /robots.txt (roadmap S38): production lets search engines in, except the admin, tools, a shopper's own pages and
 * Livewire's endpoints, and names the sitemap; every other environment (local, staging) is closed to them.
 */
final class RobotsController
{
    private const array CLOSED = ['/admin', '/horizon', '/telescope', '/livewire', '/account', '/cart', '/checkout', '/login', '/pay/', '/payment/', '/track', '/search/open/'];

    public function __invoke(): Response
    {
        $lines = app()->isProduction()
            ? ['User-agent: *', ...array_map(fn (string $path): string => 'Disallow: '.$path, self::CLOSED), '', 'Sitemap: '.route('seo.sitemap')]
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
