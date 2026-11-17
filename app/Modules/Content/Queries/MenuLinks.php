<?php

declare(strict_types=1);

namespace App\Modules\Content\Queries;

use App\Modules\Content\Enums\MenuLocation;
use App\Modules\Content\Models\Menu;
use App\Modules\Content\Models\MenuItem;
use Illuminate\Support\Facades\Cache;

/**
 * The links of a menu as the storefront shows them (roadmap S34; owner 2026-11-17): its heading and its active items
 * whose target is live, in order. Cached per location (D15 «menus»); cleared when a menu, an item or a page changes,
 * and kept at most ten minutes so a catalog item that is hidden drops out on its own.
 */
final readonly class MenuLinks
{
    private const string CACHE_KEY = 'content:menu:';

    /**
     * @return array{title: string|null, links: list<array{label: string, href: string, icon: string|null, newTab: bool}>}
     */
    public function for(MenuLocation $location): array
    {
        return Cache::remember(self::CACHE_KEY.$location->value, now()->addMinutes(10), fn (): array => $this->build($location));
    }

    public static function forget(): void
    {
        foreach (MenuLocation::cases() as $location) {
            Cache::forget(self::CACHE_KEY.$location->value);
        }
    }

    /**
     * @return array{title: string|null, links: list<array{label: string, href: string, icon: string|null, newTab: bool}>}
     */
    private function build(MenuLocation $location): array
    {
        $menu = Menu::query()
            ->where(Menu::COLUMN_LOCATION, $location)
            ->with(['items' => fn ($query) => $query->where(MenuItem::COLUMN_IS_ACTIVE, true)])
            ->first();

        if (! $menu instanceof Menu) {
            return ['title' => null, 'links' => []];
        }

        $links = [];

        foreach ($menu->getItems() as $item) {
            $href = $item->linkUrl();

            if ($href !== null) {
                $links[] = ['label' => $item->getTitle(), 'href' => $href, 'icon' => $item->getIcon()?->value, 'newTab' => $item->opensInNewTab()];
            }
        }

        return ['title' => $menu->getTitle(), 'links' => $links];
    }
}
