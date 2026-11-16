<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\Purchases\Pages;

use App\Modules\Orders\Filament\Pages\SupplierShoppingList;
use App\Modules\Orders\Filament\Resources\Purchases\OrderPurchaseResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

final class ListOrderPurchases extends ListRecords
{
    protected static string $resource = OrderPurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('shoppingList')
                ->label('خرید از تأمین‌کننده')
                ->icon(Heroicon::OutlinedShoppingCart)
                ->color('gray')
                ->url(SupplierShoppingList::getUrl()),
        ];
    }
}
