<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\AdminUsers;

use App\Modules\Identity\Filament\Resources\AdminUsers\Pages\CreateAdminUser;
use App\Modules\Identity\Filament\Resources\AdminUsers\Pages\EditAdminUser;
use App\Modules\Identity\Filament\Resources\AdminUsers\Pages\ListAdminUsers;
use App\Modules\Identity\Filament\Resources\AdminUsers\Schemas\AdminUserForm;
use App\Modules\Identity\Filament\Resources\AdminUsers\Tables\AdminUsersTable;
use App\Modules\Identity\Models\AdminUser;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Staff accounts (roadmap B6 System group). Accounts are deactivated, never deleted.
 */
final class AdminUserResource extends Resource
{
    protected static ?string $model = AdminUser::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'سیستم';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'مدیر';

    protected static ?string $pluralModelLabel = 'مدیران';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return AdminUserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdminUsersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdminUsers::route('/'),
            'create' => CreateAdminUser::route('/create'),
            'edit' => EditAdminUser::route('/{record}/edit'),
        ];
    }
}
