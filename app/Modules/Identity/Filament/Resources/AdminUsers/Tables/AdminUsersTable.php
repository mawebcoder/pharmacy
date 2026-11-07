<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\AdminUsers\Tables;

use App\Modules\Identity\Actions\ResetAdminTwoFactor;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

final class AdminUsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('roles'))
            ->columns([
                TextColumn::make(AdminUser::COLUMN_NAME)
                    ->label('نام')
                    ->searchable()
                    ->sortable(),
                TextColumn::make(AdminUser::COLUMN_EMAIL)
                    ->label('ایمیل')
                    ->searchable(),
                TextColumn::make('roles.name')
                    ->label('نقش‌ها')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => AdminRole::labelFor($state)),
                IconColumn::make('two_factor')
                    ->label('تأیید دو مرحله‌ای')
                    ->state(fn (AdminUser $record): bool => $record->hasTwoFactorAuthentication())
                    ->boolean(),
                IconColumn::make(AdminUser::COLUMN_IS_ACTIVE)
                    ->label('فعال')
                    ->boolean(),
                TextColumn::make(AdminUser::COLUMN_LAST_LOGIN_AT)
                    ->label('آخرین ورود')
                    ->jalaliDateTime() // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make(AdminUser::COLUMN_IS_ACTIVE)->label('فعال'),
                SelectFilter::make('roles')
                    ->label('نقش')
                    ->relationship('roles', 'name')
                    ->getOptionLabelFromRecordUsing(fn (Role $record): string => AdminRole::labelFor($record->name)),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('resetTwoFactor')
                    ->label('بازنشانی تأیید دو مرحله‌ای')
                    ->icon(Heroicon::OutlinedKey)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('اپلیکیشن تأییدکننده و کدهای بازیابی این مدیر پاک می‌شود و در ورود بعدی باید دوباره آن را راه‌اندازی کند.')
                    ->visible(fn (AdminUser $record): bool => $record->hasTwoFactorAuthentication())
                    ->authorize(fn (AdminUser $record): bool => auth('admin')->user()?->can('resetTwoFactor', $record) ?? false)
                    ->action(function (AdminUser $record, ResetAdminTwoFactor $resetTwoFactor): void {
                        $resetTwoFactor($record);

                        Notification::make()->success()->title('تأیید دو مرحله‌ای بازنشانی شد.')->send();
                    }),
            ])
            ->defaultSort(AdminUser::COLUMN_NAME);
    }
}
