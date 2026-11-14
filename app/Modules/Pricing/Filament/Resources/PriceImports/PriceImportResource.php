<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Filament\Resources\PriceImports;

use App\Modules\Identity\Queries\AdminNames;
use App\Modules\Pricing\Enums\PriceImportStatus;
use App\Modules\Pricing\Filament\Resources\PriceImports\Pages\ListPriceImports;
use App\Modules\Pricing\Filament\Resources\PriceImports\Pages\ViewPriceImport;
use App\Modules\Pricing\Filament\Resources\PriceImports\RelationManagers\PriceImportRowsRelationManager;
use App\Modules\Pricing\Models\PriceImport;
use App\Modules\Shared\Support\PersianDigits;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Supplier price imports from Excel («ورودهای قیمت», roadmap D13.9, S16): each upload, its status and counts. New
 * imports start from the suppliers list («ورود قیمت از اکسل»); each one's preview and «اعمال» are on its view page.
 */
final class PriceImportResource extends Resource
{
    protected static ?string $model = PriceImport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static string|UnitEnum|null $navigationGroup = 'قیمت‌گذاری';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'ورود قیمت';

    protected static ?string $pluralModelLabel = 'ورودهای قیمت';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        $count = fn (int $number): string => PersianDigits::toPersian($number);

        return $schema
            ->columns(1)
            ->components([
                Section::make('فایل')
                    ->columns(4)
                    ->schema([
                        TextEntry::make(PriceImport::COLUMN_ORIGINAL_NAME)->label('نام فایل'),
                        TextEntry::make(PriceImport::COLUMN_STATUS)
                            ->label('وضعیت')
                            ->badge()
                            ->formatStateUsing(fn (PriceImportStatus $state): string => $state->label())
                            ->color(fn (PriceImportStatus $state): string => $state->color()),
                        TextEntry::make(PriceImport::COLUMN_ADMIN_ID)
                            ->label('بارگذاری توسط')
                            ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : (app(AdminNames::class)->for([$state])[$state] ?? 'مدیر حذف‌شده')),
                        TextEntry::make(PriceImport::COLUMN_CREATED_AT)->label('زمان بارگذاری')->jalaliDateTime(), // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
                        TextEntry::make(PriceImport::COLUMN_ROWS_TOTAL)->label('ردیف‌ها')->formatStateUsing(fn (int $state): string => $count($state)),
                        TextEntry::make(PriceImport::COLUMN_ROWS_MATCHED)->label('قابل ورود')->formatStateUsing(fn (int $state): string => $count($state))->color('success'),
                        TextEntry::make(PriceImport::COLUMN_ROWS_FAILED)->label('مشکل‌دار')->formatStateUsing(fn (int $state): string => $count($state))->color(fn (int $state): ?string => $state > 0 ? 'danger' : null),
                        TextEntry::make(PriceImport::COLUMN_ROWS_UPDATED)->label('اعمال‌شده')->formatStateUsing(fn (int $state): string => $count($state)),
                        TextEntry::make(PriceImport::COLUMN_ERROR)->label('خطا')->color('danger')->columnSpanFull()->visible(fn (PriceImport $record): bool => $record->getError() !== null),
                        // While the file is being read or applied, the page refreshes itself.
                        View::make('pricing::filament.poll-while-busy')
                            ->visible(fn (PriceImport $record): bool => in_array($record->getStatus(), [PriceImportStatus::Pending, PriceImportStatus::Processing, PriceImportStatus::Applying], true))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort(PriceImport::COLUMN_ID, 'desc')
            ->poll('10s')
            ->columns([
                TextColumn::make(PriceImport::COLUMN_ORIGINAL_NAME)->label('فایل')->searchable(),
                TextColumn::make(PriceImport::COLUMN_STATUS)
                    ->label('وضعیت')
                    ->badge()
                    ->formatStateUsing(fn (PriceImportStatus $state): string => $state->label())
                    ->color(fn (PriceImportStatus $state): string => $state->color()),
                TextColumn::make(PriceImport::COLUMN_ROWS_TOTAL)->label('ردیف‌ها')->formatStateUsing(fn (int $state): string => PersianDigits::toPersian($state)),
                TextColumn::make(PriceImport::COLUMN_ROWS_FAILED)->label('مشکل‌دار')->formatStateUsing(fn (int $state): string => PersianDigits::toPersian($state))->color(fn (int $state): ?string => $state > 0 ? 'danger' : null),
                TextColumn::make(PriceImport::COLUMN_ROWS_UPDATED)->label('اعمال‌شده')->formatStateUsing(fn (int $state): string => PersianDigits::toPersian($state)),
                TextColumn::make(PriceImport::COLUMN_CREATED_AT)->label('زمان')->jalaliDateTime()->sortable(), // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
            ])
            ->recordActions([ViewAction::make()])
            ->emptyStateHeading('هنوز فایلی وارد نشده است.')
            ->emptyStateDescription('از فهرست تأمین‌کنندگان، «ورود قیمت از اکسل» را بزنید.');
    }

    public static function getRelations(): array
    {
        return [PriceImportRowsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPriceImports::route('/'),
            'view' => ViewPriceImport::route('/{record}'),
        ];
    }
}
