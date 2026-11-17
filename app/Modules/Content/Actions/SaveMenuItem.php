<?php

declare(strict_types=1);

namespace App\Modules\Content\Actions;

use App\Modules\Content\Data\MenuItemData;
use App\Modules\Content\Models\MenuItem;

/**
 * Adds a link to a menu (at its end) or changes one (roadmap S34; owner 2026-11-17).
 */
final class SaveMenuItem
{
    #[\NoDiscard]
    public function __invoke(MenuItemData $data, ?MenuItem $item = null): MenuItem
    {
        if (! $item instanceof MenuItem) {
            $item = (new MenuItem)
                ->setMenuId($data->menuId)
                ->setSort((int) MenuItem::query()->where(MenuItem::COLUMN_MENU_ID, $data->menuId)->max(MenuItem::COLUMN_SORT) + 1);
        }

        $item->setTitle($data->title)
            ->setIcon($data->icon)
            ->setLinkType($data->linkType)
            ->setLinkId($data->linkId)
            ->setExternalUrl($data->externalUrl)
            ->setIsActive($data->isActive)
            ->save();

        return $item;
    }
}
