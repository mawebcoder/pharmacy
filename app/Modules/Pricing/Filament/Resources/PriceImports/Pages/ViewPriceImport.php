<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Filament\Resources\PriceImports\Pages;

use App\Modules\Pricing\Actions\ConfirmPriceImport;
use App\Modules\Pricing\Enums\PriceImportStatus;
use App\Modules\Pricing\Exceptions\PriceImportRefused;
use App\Modules\Pricing\Filament\Resources\PriceImports\PriceImportResource;
use App\Modules\Pricing\Models\PriceImport;
use App\Modules\Pricing\Models\PriceImportRow;
use App\Modules\Pricing\Support\PriceSpreadsheet;
use App\Modules\Shared\Support\PersianDigits;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * One import's preview (roadmap D13.9): its counts, the checked rows (PriceImportRowsRelationManager), «اعمال» to
 * save the rows in the background, and the error report of the rows that cannot be imported.
 */
final class ViewPriceImport extends ViewRecord
{
    protected static string $resource = PriceImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('apply')
                ->label('اعمال')
                ->icon(Heroicon::OutlinedCheck)
                ->color('success')
                ->visible(fn (): bool => $this->import()->getStatus() === PriceImportStatus::Previewed && (auth('admin')->user()?->can('update', $this->import()) ?? false))
                ->requiresConfirmation()
                ->modalHeading('اعمال قیمت‌های این فایل')
                ->modalDescription(fn (): string => PersianDigits::toPersian($this->changingRows()).' ردیف تغییر می‌کند و قیمت فروش SKUها در پس‌زمینه دوباره محاسبه می‌شود. ردیف‌های مشکل‌دار و ردیف‌هایی که در فایل نیستند تغییری نمی‌کنند.')
                ->action(function (ConfirmPriceImport $confirm): void {
                    try {
                        $confirm($this->import());
                    } catch (PriceImportRefused $refused) {
                        Notification::make()->danger()->title($refused->getMessage())->send();

                        return;
                    }

                    Notification::make()->success()->title('در حال اعمال؛ چند ثانیه بعد وضعیت «اعمال شد» می‌شود.')->send();
                    $this->getRecord()->refresh();
                }),
            Action::make('errors')
                ->label('دانلود ردیف‌های مشکل‌دار')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->visible(fn (): bool => $this->import()->getRowsFailed() > 0)
                ->action(function (): BinaryFileResponse {
                    $path = tempnam(sys_get_temp_dir(), 'price-errors-').'.xlsx';
                    PriceSpreadsheet::writeErrors($path, $this->failedRows());

                    return response()->download($path, 'price-import-'.$this->import()->getId().'-errors.xlsx')->deleteFileAfterSend();
                }),
        ];
    }

    private function import(): PriceImport
    {
        /** @var PriceImport $import */
        $import = $this->getRecord();

        return $import;
    }

    private function changingRows(): int
    {
        return $this->import()->rows()->whereNull(PriceImportRow::COLUMN_ERROR)->where(PriceImportRow::COLUMN_CHANGES, true)->count();
    }

    /**
     * @return \Generator<array{0: list<string>, 1: string}>
     */
    private function failedRows(): \Generator
    {
        foreach ($this->import()->rows()->whereNotNull(PriceImportRow::COLUMN_ERROR)->orderBy(PriceImportRow::COLUMN_ROW_NUMBER)->lazy(500) as $row) {
            yield [$row->getRaw(), 'ردیف '.$row->getRowNumber().': '.$row->getError()];
        }
    }
}
