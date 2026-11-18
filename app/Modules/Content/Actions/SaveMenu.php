<?php

declare(strict_types=1);

namespace App\Modules\Content\Actions;

use App\Modules\Content\Models\Menu;
use App\Modules\Shared\Support\PersianNormalizer;

/**
 * Changes a menu's heading (roadmap S29): the footer column's title; empty shows none.
 */
final class SaveMenu
{
    public function __invoke(Menu $menu, ?string $title): void
    {
        $title = PersianNormalizer::normalize(trim(strip_tags((string) $title)));

        $menu->setTitle($title === '' ? null : $title)->save();
    }
}
