<?php

declare(strict_types=1);

namespace App\Modules\Seo;

use App\Modules\Seo\Listeners\RecordSlugRedirects;
use App\Modules\Shared\ModuleServiceProvider;
use Illuminate\Support\Facades\Event;

/**
 * SEO (roadmap S38): <x-seo::head> in the layout, sitemaps and robots.txt (Routes/web.php), and redirects: old
 * addresses are sent on before routing (RedirectOldPaths, a global middleware in bootstrap/app.php), and a slug change
 * records its own redirect.
 */
final class SeoServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        // Any model with slug addresses (Shared\Contracts\HasSlugAddresses), without naming other modules' models.
        Event::listen('eloquent.updated: *', [RecordSlugRedirects::class, 'handle']);
    }
}
