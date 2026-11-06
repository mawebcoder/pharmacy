<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\Roles;

use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Filament\Resources\Roles\Pages\CreateRole;
use App\Modules\Identity\Filament\Resources\Roles\Pages\EditRole;
use App\Modules\Identity\Filament\Resources\Roles\Pages\ListRoles;
use App\Modules\Identity\Filament\Resources\Roles\Pages\ViewRole;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource as ShieldRoleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Override;
use Spatie\Permission\Models\Role;

/**
 * Shield's role page with Persian role names and Jalali dates. Built-in roles (AdminRole) cannot be deleted.
 * Because this class is registered, Shield does not register its own RoleResource.
 */
final class RoleResource extends ShieldRoleResource
{
    #[Override]
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('نقش')
                    ->weight(FontWeight::Medium)
                    ->formatStateUsing(fn (string $state): string => AdminRole::labelFor($state))
                    ->searchable(),
                TextColumn::make('permissions_count')
                    ->label('تعداد مجوزها')
                    ->badge()
                    ->counts('permissions')
                    ->color('primary'),
                TextColumn::make('updated_at')
                    ->label('آخرین تغییر')
                    ->jalaliDateTime(), // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn (Role $record): bool => AdminRole::isBuiltIn($record->name)),
            ]);
    }

    #[Override]
    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record instanceof Role ? AdminRole::labelFor($record->name) : parent::getRecordTitle($record);
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'view' => ViewRole::route('/{record}'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }
}
