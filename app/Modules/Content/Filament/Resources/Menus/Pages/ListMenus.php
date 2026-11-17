<?php

declare(strict_types=1);

namespace App\Modules\Content\Filament\Resources\Menus\Pages;

use App\Modules\Content\Filament\Resources\Menus\MenuResource;
use Filament\Resources\Pages\ListRecords;

final class ListMenus extends ListRecords
{
    protected static string $resource = MenuResource::class;
}
