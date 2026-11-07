<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Categories\Pages;

use App\Modules\Catalog\Filament\Resources\Categories\CategoryResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

final class ListCategories extends ListRecords
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('tree')
                ->label('درخت دسته‌ها')
                ->icon(Heroicon::OutlinedSquares2x2)
                ->color('gray')
                ->url(CategoryResource::getUrl()),
            CreateAction::make(),
        ];
    }
}
