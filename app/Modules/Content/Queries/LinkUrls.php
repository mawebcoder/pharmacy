<?php

declare(strict_types=1);

namespace App\Modules\Content\Queries;

use App\Modules\Catalog\Queries\CatalogLinks;
use App\Modules\Content\Enums\LinkType;
use Illuminate\Support\Facades\Route;

/**
 * The address a banner or menu item leads to now (roadmap S13b, S34), or null when its target is gone or hidden (the
 * banner or item is then not shown).
 */
final readonly class LinkUrls
{
    public function __construct(
        private CatalogLinks $catalogLinks,
        private PageLinks $pageLinks,
    ) {}

    public function url(LinkType $type, ?int $linkId, ?string $externalUrl): ?string
    {
        $route = $type->routeName();

        return match (true) {
            $route !== null => Route::has($route) ? route($route) : null,
            $type === LinkType::External => $externalUrl,
            $linkId === null => null,
            $type === LinkType::Page => $this->pageLinks->url($linkId),
            default => $this->catalogLinks->url($type->value, $linkId),
        };
    }

    /**
     * What the target is, for admin lists: the picked item's name («حذف‌شده» when gone), the external address, or the
     * site page's name.
     */
    public function label(LinkType $type, ?int $linkId, ?string $externalUrl): string
    {
        return match (true) {
            $type === LinkType::External => (string) $externalUrl,
            $type === LinkType::Page && $linkId !== null => $this->pageLinks->label($linkId) ?? 'حذف‌شده',
            $type->isCatalogItem() && $linkId !== null => $this->catalogLinks->label($type->value, $linkId) ?? 'حذف‌شده',
            default => $type->label(),
        };
    }
}
