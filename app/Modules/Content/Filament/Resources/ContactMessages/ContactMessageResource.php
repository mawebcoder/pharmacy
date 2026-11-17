<?php

declare(strict_types=1);

namespace App\Modules\Content\Filament\Resources\ContactMessages;

use App\Modules\Content\Enums\ContactMessageStatus;
use App\Modules\Content\Enums\ContactSubject;
use App\Modules\Content\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Modules\Content\Filament\Resources\ContactMessages\Pages\ViewContactMessage;
use App\Modules\Content\Models\ContactMessage;
use App\Modules\Shared\Support\PersianDigits;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * «پیام‌های تماس» (roadmap D3.6, S34): messages from /contact, newest first, with the count of new ones on the menu.
 * Opening one marks it read; staff reply by phone or email and mark it answered.
 */
final class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|UnitEnum|null $navigationGroup = 'محتوا';

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'پیام تماس';

    protected static ?string $pluralModelLabel = 'پیام‌های تماس';

    public static function getNavigationBadge(): ?string
    {
        $new = ContactMessage::query()->where(ContactMessage::COLUMN_STATUS, ContactMessageStatus::New)->count();

        return $new > 0 ? PersianDigits::toPersian($new) : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('فرستنده')
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                ->schema([
                    TextEntry::make(ContactMessage::COLUMN_NAME)->label('نام'),
                    TextEntry::make(ContactMessage::COLUMN_MOBILE)
                        ->label('موبایل')
                        ->url(fn (ContactMessage $record): string => 'tel:'.$record->getMobile())
                        ->extraAttributes(['dir' => 'ltr'])
                        ->copyable(),
                    TextEntry::make(ContactMessage::COLUMN_EMAIL)
                        ->label('ایمیل')
                        ->placeholder('—')
                        ->url(fn (ContactMessage $record): ?string => $record->getEmail() === null ? null : 'mailto:'.$record->getEmail())
                        ->extraAttributes(['dir' => 'ltr']),
                    TextEntry::make(ContactMessage::COLUMN_CREATED_AT)
                        ->label('ارسال')
                        ->jalaliDateTime(), // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
                ]),
            Section::make('پیام')
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextEntry::make(ContactMessage::COLUMN_SUBJECT)
                        ->label('موضوع')
                        ->formatStateUsing(fn (ContactSubject $state): string => $state->label()),
                    TextEntry::make(ContactMessage::COLUMN_STATUS)
                        ->label('وضعیت')
                        ->badge()
                        ->formatStateUsing(fn (ContactMessageStatus $state): string => $state->label())
                        ->color(fn (ContactMessageStatus $state): string => $state->color()),
                    TextEntry::make(ContactMessage::COLUMN_BODY)
                        ->label('متن')
                        ->columnSpanFull()
                        ->extraAttributes(['class' => 'whitespace-pre-line']),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort(ContactMessage::COLUMN_CREATED_AT, 'desc')
            ->columns([
                TextColumn::make(ContactMessage::COLUMN_NAME)
                    ->label('فرستنده')
                    ->description(fn (ContactMessage $record): string => $record->getMobile())
                    ->searchable([ContactMessage::COLUMN_NAME, ContactMessage::COLUMN_MOBILE]),
                TextColumn::make(ContactMessage::COLUMN_SUBJECT)
                    ->label('موضوع')
                    ->formatStateUsing(fn (ContactSubject $state): string => $state->label()),
                TextColumn::make(ContactMessage::COLUMN_BODY)->label('متن')->limit(60),
                TextColumn::make(ContactMessage::COLUMN_STATUS)
                    ->label('وضعیت')
                    ->badge()
                    ->formatStateUsing(fn (ContactMessageStatus $state): string => $state->label())
                    ->color(fn (ContactMessageStatus $state): string => $state->color()),
                TextColumn::make(ContactMessage::COLUMN_CREATED_AT)
                    ->label('ارسال')
                    ->jalaliDateTime() // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make(ContactMessage::COLUMN_STATUS)->label('وضعیت')->options(ContactMessageStatus::options()),
                SelectFilter::make(ContactMessage::COLUMN_SUBJECT)->label('موضوع')->options(ContactSubject::options()),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContactMessages::route('/'),
            'view' => ViewContactMessage::route('/{record}'),
        ];
    }
}
