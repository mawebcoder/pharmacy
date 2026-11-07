<?php

declare(strict_types=1);

namespace App\Modules\Search\Filament\Resources\SearchSynonyms\Pages;

use App\Modules\Search\Actions\ImportSearchSynonyms;
use App\Modules\Search\Actions\SaveSearchSynonym;
use App\Modules\Search\Filament\Resources\SearchSynonyms\SearchSynonymResource;
use App\Modules\Search\Models\SearchSynonym;
use App\Modules\Search\Support\SynonymSpreadsheet;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Synonyms list with create, Excel import (merges into existing phrases) and a sample file.
 */
final class ManageSearchSynonyms extends ManageRecords
{
    protected static string $resource = SearchSynonymResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label('ورود از اکسل')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('gray')
                ->visible(fn (): bool => auth('admin')->user()?->can('Create:SearchSynonym') ?? false)
                ->modalDescription('ستون اول عبارت است و هر خانه بعدی یک هم‌معنی (یا چند هم‌معنی جداشده با ویرگول). عبارت‌های موجود ادغام می‌شوند.')
                ->schema([
                    FileUpload::make('file')
                        ->label('فایل اکسل یا CSV')
                        ->disk('local')
                        ->directory('imports/synonyms')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'text/csv',
                            'text/plain',
                            'application/csv',
                        ])
                        ->maxSize(2048)
                        ->required(),
                ])
                ->action(function (array $data, ImportSearchSynonyms $import): void {
                    $path = Storage::disk('local')->path((string) $data['file']);

                    try {
                        $result = $import($path, pathinfo($path, PATHINFO_EXTENSION));
                    } catch (Throwable $exception) {
                        Notification::make()->danger()->title('فایل خوانده نشد.')->body($exception->getMessage())->persistent()->send();

                        return;
                    } finally {
                        Storage::disk('local')->delete((string) $data['file']);
                    }

                    $notification = Notification::make()->title($result->summary());

                    if ($result->skipped === []) {
                        $notification->success()->send();

                        return;
                    }

                    $notification->warning()->body($result->skippedDetails())->persistent()->send();
                }),
            Action::make('template')
                ->label('دانلود نمونه')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(function (): BinaryFileResponse {
                    $path = tempnam(sys_get_temp_dir(), 'synonyms-').'.xlsx';
                    SynonymSpreadsheet::writeTemplate($path);

                    return response()->download($path, 'synonyms-template.xlsx')->deleteFileAfterSend();
                }),
            CreateAction::make()
                ->using(fn (array $data, SaveSearchSynonym $saveSynonym): SearchSynonym => $saveSynonym(
                    (string) $data[SearchSynonym::COLUMN_TERM],
                    array_values(array_map(strval(...), (array) $data[SearchSynonym::COLUMN_SYNONYMS])),
                )),
        ];
    }
}
