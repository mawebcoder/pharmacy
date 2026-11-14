<?php

declare(strict_types=1);

namespace App\Modules\Content\Queries;

use App\Modules\Content\Models\Page;

/**
 * Static pages as banner targets (roadmap S34): picker options by title, labels, and the URL of a page that is live.
 */
final readonly class PageLinks
{
    /**
     * id => «title» («(پیش‌نویس)» added to pages not yet published), for the banner form.
     *
     * @return array<int, string>
     */
    public function options(): array
    {
        return Page::query()
            ->orderBy(Page::COLUMN_TITLE)
            ->get([Page::COLUMN_ID, Page::COLUMN_TITLE, Page::COLUMN_IS_ACTIVE])
            ->mapWithKeys(fn (Page $page): array => [$page->getId() => $page->getTitle().($page->isActive() ? '' : ' (پیش‌نویس)')])
            ->all();
    }

    public function label(int $pageId): ?string
    {
        return Page::query()->find($pageId)?->getTitle();
    }

    /**
     * The page's address now, or null when it is unpublished or deleted (the banner is then not shown).
     */
    public function url(int $pageId): ?string
    {
        return Page::query()->active()->find($pageId)?->url();
    }
}
