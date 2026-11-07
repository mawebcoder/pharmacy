<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\AdminUsers\Schemas;

use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Http\Requests\StoreAdminUserRequest;
use App\Modules\Identity\Models\AdminUser;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Role;

/**
 * Field rules come from StoreAdminUserRequest::baseRules() so admin and HTTP validation never drift (roadmap C5).
 */
final class AdminUserForm
{
    public static function configure(Schema $schema): Schema
    {
        $rules = fn (?AdminUser $record, string $field): array => StoreAdminUserRequest::baseRules($record?->getId())[$field];

        return $schema
            ->components([
                Section::make('حساب کاربری')
                    ->columns(2)
                    ->schema([
                        TextInput::make(AdminUser::COLUMN_NAME)
                            ->label('نام')
                            ->required()
                            ->rules(fn (?AdminUser $record): array => $rules($record, AdminUser::COLUMN_NAME)),
                        TextInput::make(AdminUser::COLUMN_EMAIL)
                            ->label('ایمیل')
                            ->email()
                            ->required()
                            ->rules(fn (?AdminUser $record): array => $rules($record, AdminUser::COLUMN_EMAIL)),
                        TextInput::make(AdminUser::COLUMN_PASSWORD)
                            ->label('رمز عبور')
                            ->password()
                            ->revealable()
                            ->required(fn (?AdminUser $record): bool => ! $record instanceof AdminUser)
                            ->rules(fn (?AdminUser $record): array => $rules($record, AdminUser::COLUMN_PASSWORD))
                            ->helperText(fn (?AdminUser $record): string => ($record instanceof AdminUser ? 'برای تغییر ندادن رمز، خالی بگذارید. ' : '')
                                .'دست‌کم ۱۲ کاراکتر، با حروف بزرگ و کوچک، عدد و نماد.'),
                        Select::make('roles')
                            ->label('نقش‌ها')
                            ->multiple()
                            ->options(fn (): array => Role::query()
                                ->where('guard_name', 'admin')
                                ->orderBy('name')
                                ->pluck('name', 'name')
                                ->map(AdminRole::labelFor(...))
                                ->all())
                            ->rules(fn (?AdminUser $record): array => $rules($record, 'roles'))
                            ->nestedRecursiveRules(StoreAdminUserRequest::baseRules()['roles.*']),
                        Toggle::make(AdminUser::COLUMN_IS_ACTIVE)
                            ->label('فعال')
                            ->helperText('مدیر غیرفعال نمی‌تواند وارد پنل شود.')
                            ->default(true),
                    ]),
            ]);
    }
}
