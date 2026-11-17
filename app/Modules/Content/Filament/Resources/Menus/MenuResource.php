<?php

declare(strict_types=1);

namespace App\Modules\Content\Filament\Resources\Menus;

use App\Modules\Content\Enums\MenuLocation;
use App\Modules\Content\Filament\Resources\Menus\Pages\EditMenu;
use App\Modules\Content\Filament\Resources\Menus\Pages\ListMenus;
use App\Modules\Content\Filament\Resources\Menus\RelationManagers\MenuItemsRelationManager;
use App\Modules\Content\Models\Menu;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * «منوها» (roadmap D3.6, S34; owner 2026-11-17): the header links beside «دسته‌بندی کالاها», the three footer columns
 * and the phone and tablet menu. The five menus come with the migration; the admin edits their headings and links.
 */
final class MenuResource extends Resource
{
    protected static ?string $model = Menu::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3;

    protected static string|UnitEnum|null $navigationGroup = 'محتوا';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'منو';

    protected static ?string $pluralModelLabel = 'منوها';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->description(fn (Menu $record): string => $record->getLocation()->hint())
                    ->columns(2)
                    ->schema([
                        TextInput::make(Menu::COLUMN_TITLE)
                            ->label('عنوان منو')
                            ->maxLength(60)
                            ->helperText('عنوان ستون پانویس؛ خالی یعنی بدون عنوان.')
                            ->visible(fn (Menu $record): bool => $record->getLocation()->isFooter()),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('items'))
            ->paginated(false)
            ->columns([
                TextColumn::make(Menu::COLUMN_LOCATION)
                    ->label('جایگاه')
                    ->formatStateUsing(fn (MenuLocation $state): string => $state->label())
                    ->description(fn (Menu $record): string => $record->getLocation()->hint()),
                TextColumn::make(Menu::COLUMN_TITLE)->label('عنوان')->placeholder('—'),
                TextColumn::make('items_count')->label('پیوندها')->numeric(),
            ])
            ->recordActions([EditAction::make()->label('ویرایش پیوندها')])
            ->defaultSort('id');
    }

    public static function getRelations(): array
    {
        return [MenuItemsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMenus::route('/'),
            'edit' => EditMenu::route('/{record}/edit'),
        ];
    }
}
