<?php

declare(strict_types=1);

namespace App\Modules\Content\Filament\Resources\Menus\Pages;

use App\Modules\Content\Actions\SaveMenu;
use App\Modules\Content\Filament\Resources\Menus\MenuResource;
use App\Modules\Content\Models\Menu;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditMenu extends EditRecord
{
    protected static string $resource = MenuResource::class;

    public function getTitle(): string
    {
        /** @var Menu $menu */
        $menu = $this->getRecord();

        return 'منو: '.$menu->getLocation()->label();
    }

    /**
     * Only footer menus have a heading; the others are just their links.
     */
    protected function getFormActions(): array
    {
        /** @var Menu $menu */
        $menu = $this->getRecord();

        return $menu->getLocation()->isFooter() ? parent::getFormActions() : [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Menu $record */
        app(SaveMenu::class)($record, isset($data[Menu::COLUMN_TITLE]) ? (string) $data[Menu::COLUMN_TITLE] : null);

        return $record;
    }
}
