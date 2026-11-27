<?php

declare(strict_types=1);

namespace App\Modules\Content\Queries;

use App\Modules\Content\Models\Page;
use Illuminate\Support\Carbon;

/**
 * The admin's active static pages for sitemap.xml (roadmap S38).
 */
final readonly class PageAddresses
{
    /**
     * @return list<array{loc: string, lastmod: ?Carbon}>
     */
    public function active(): array
    {
        return array_values(Page::query()->active()->get()
            ->map(fn (Page $page): array => ['loc' => route('pages.show', $page->getSlug()), 'lastmod' => $page->getUpdatedAt()])
            ->all());
    }
}
