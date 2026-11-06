<?php

declare(strict_types=1);

namespace App\Modules\System\Filament\Resources\ActivityLogs\Schemas;

use App\Modules\System\Filament\Resources\ActivityLogs\Tables\ActivityLogsTable;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Novadaemon\FilamentPrettyJson\Infolist\PrettyJsonEntry;
use Spatie\Activitylog\Models\Activity;

/**
 * One activity with its raw changes as readable JSON (old and new values, roles, permissions).
 */
final class ActivityLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('جزئیات')
                    ->columnSpanFull()
                    ->columns(4)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('زمان')
                            ->jalaliDateTime(), // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
                        TextEntry::make('causer.name')
                            ->label('انجام‌دهنده')
                            ->placeholder('سیستم'),
                        TextEntry::make('event')
                            ->label('رویداد')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => ActivityLogsTable::eventLabel($state)),
                        TextEntry::make('subject_label')
                            ->label('مورد')
                            ->state(fn (Activity $record): string => ActivityLogsTable::subjectLabel($record)),
                    ]),
                Section::make('تغییرات')
                    ->columnSpanFull()
                    ->schema([
                        PrettyJsonEntry::make('attribute_changes')
                            ->label('مقادیر قبلی (old) و جدید (attributes)')
                            ->state(fn (Activity $record): ?string => self::json($record->attribute_changes))
                            ->visible(fn (Activity $record): bool => filled($record->attribute_changes?->all()))
                            ->copyable()
                            ->extraAttributes(['dir' => 'ltr']),
                        PrettyJsonEntry::make('properties')
                            ->label('اطلاعات بیشتر')
                            ->state(fn (Activity $record): ?string => self::json($record->properties))
                            ->visible(fn (Activity $record): bool => filled($record->properties?->all()))
                            ->copyable()
                            ->extraAttributes(['dir' => 'ltr']),
                    ]),
            ]);
    }

    /**
     * Keeps Persian text readable instead of \uXXXX escapes.
     *
     * @param  Collection<array-key, mixed>|null  $values
     */
    public static function json(?Collection $values): ?string
    {
        if (! $values instanceof Collection) {
            return null;
        }

        return json_encode($values->all(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
    }
}
