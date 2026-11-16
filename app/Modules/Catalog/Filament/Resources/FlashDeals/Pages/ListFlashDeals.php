<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\FlashDeals\Pages;

use App\Modules\Catalog\Filament\Resources\FlashDeals\FlashDealResource;
use App\Modules\Catalog\Models\FlashDeal;
use App\Modules\Content\Actions\SetFlashDealWidget;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

/**
 * The flash deals, with the button that turns the home page's «پیشنهاد لحظه‌ای» row on or off (owner, 2026-11-17).
 */
final class ListFlashDeals extends ListRecords
{
    protected static string $resource = FlashDealResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('homeRow')
                ->label(fn (): string => SetFlashDealWidget::isOn() ? 'نمایش در صفحه اصلی: روشن' : 'نمایش در صفحه اصلی: خاموش')
                ->icon(fn (): Heroicon => SetFlashDealWidget::isOn() ? Heroicon::OutlinedEye : Heroicon::OutlinedEyeSlash)
                ->color(fn (): string => SetFlashDealWidget::isOn() ? 'success' : 'gray')
                ->tooltip(fn (): string => SetFlashDealWidget::isOn() ? 'برای خاموش کردن ردیف صفحه اصلی کلیک کنید.' : 'برای روشن کردن ردیف صفحه اصلی کلیک کنید.')
                ->visible(fn (): bool => auth('admin')->user()?->can('create', FlashDeal::class) ?? false)
                ->action(function (SetFlashDealWidget $setWidget): void {
                    $on = ! SetFlashDealWidget::isOn();
                    $setWidget($on);
                    Notification::make()->success()->title($on ? 'ردیف «پیشنهاد لحظه‌ای» در صفحه اصلی روشن شد.' : 'ردیف «پیشنهاد لحظه‌ای» از صفحه اصلی برداشته شد.')->send();
                }),
            CreateAction::make(),
        ];
    }
}
