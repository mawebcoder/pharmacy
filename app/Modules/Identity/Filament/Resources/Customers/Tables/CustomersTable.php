<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\Customers\Tables;

use App\Modules\Identity\Actions\SetCustomerBlocked;
use App\Modules\Identity\Filament\Resources\Customers\CustomerResource;
use App\Modules\Identity\Models\Customer;
use App\Modules\Shared\Support\IranMobile;
use App\Modules\Shared\Support\PersianNormalizer;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make(Customer::COLUMN_MOBILE)
                    ->label('موبایل')
                    ->extraAttributes(['dir' => 'ltr'])
                    ->copyable()
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(
                        Customer::COLUMN_MOBILE,
                        'like',
                        '%'.(IranMobile::normalize($search) ?? preg_replace('/\D/', '', PersianNormalizer::digits($search))).'%',
                    )),
                TextColumn::make('name')
                    ->label('نام')
                    ->state(fn (Customer $record): ?string => trim(($record->getFirstName() ?? '').' '.($record->getLastName() ?? '')) ?: null)
                    ->placeholder('—')
                    ->searchable([Customer::COLUMN_FIRST_NAME, Customer::COLUMN_LAST_NAME]),
                IconColumn::make(Customer::COLUMN_IS_BLOCKED)
                    ->label('مسدود')
                    ->boolean()
                    ->trueColor('danger')
                    ->falseColor('gray')
                    ->trueIcon(Heroicon::OutlinedNoSymbol)
                    ->falseIcon(Heroicon::OutlinedMinus),
                TextColumn::make(Customer::COLUMN_LAST_LOGIN_AT)
                    ->label('آخرین ورود')
                    ->jalaliDateTime() // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make(Customer::COLUMN_CREATED_AT)
                    ->label('عضویت')
                    ->jalaliDate() // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make(Customer::COLUMN_IS_BLOCKED)->label('مسدود'),
            ])
            ->recordUrl(fn (Customer $record): string => CustomerResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                self::blockAction(),
                self::unblockAction(),
            ])
            ->defaultSort(Customer::COLUMN_CREATED_AT, 'desc');
    }

    public static function blockAction(): Action
    {
        return Action::make('block')
            ->label('مسدود کردن')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('این مشتری دیگر نمی‌تواند وارد شود و ورودهای ماندگار او پایان می‌یابد.')
            ->visible(fn (Customer $record): bool => ! $record->isBlocked())
            ->authorize(fn (Customer $record): bool => auth('admin')->user()?->can('block', $record) ?? false)
            ->action(function (Customer $record, SetCustomerBlocked $setBlocked): void {
                $setBlocked($record, true);

                Notification::make()->success()->title('مشتری مسدود شد.')->send();
            });
    }

    public static function unblockAction(): Action
    {
        return Action::make('unblock')
            ->label('رفع مسدودی')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (Customer $record): bool => $record->isBlocked())
            ->authorize(fn (Customer $record): bool => auth('admin')->user()?->can('block', $record) ?? false)
            ->action(function (Customer $record, SetCustomerBlocked $setBlocked): void {
                $setBlocked($record, false);

                Notification::make()->success()->title('مسدودی مشتری برداشته شد.')->send();
            });
    }
}
