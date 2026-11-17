<?php

declare(strict_types=1);

namespace App\Modules\Content\Filament\Resources\Menus\RelationManagers;

use App\Modules\Content\Actions\DeleteMenuItem;
use App\Modules\Content\Actions\ReorderMenuItems;
use App\Modules\Content\Actions\SaveMenuItem;
use App\Modules\Content\Data\MenuItemData;
use App\Modules\Content\Enums\MenuIcon;
use App\Modules\Content\Filament\Forms\LinkTargetFields;
use App\Modules\Content\Http\Requests\SaveMenuItemRequest;
use App\Modules\Content\Models\Menu;
use App\Modules\Content\Models\MenuItem;
use App\Modules\Content\Queries\LinkUrls;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * A menu's links (roadmap S34; owner 2026-11-17): title, icon, where it leads (picked like a banner's target) and an
 * on/off switch, dragged into order. Writes go through the Content actions.
 */
final class MenuItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'پیوندها';

    protected static ?string $modelLabel = 'پیوند';

    protected static ?string $pluralModelLabel = 'پیوندها';

    public function form(Schema $schema): Schema
    {
        $rules = SaveMenuItemRequest::baseRules();

        return $schema
            ->columns(2)
            ->components([
                TextInput::make(MenuItem::COLUMN_TITLE)
                    ->label('عنوان')
                    ->required()
                    ->maxLength(60)
                    ->rules($rules[MenuItem::COLUMN_TITLE]),
                Select::make(MenuItem::COLUMN_ICON)
                    ->label('آیکن')
                    ->options(self::iconOptions())
                    ->allowHtml()
                    ->searchable()
                    ->placeholder('بدون آیکن')
                    ->helperText('در سربرگ و منوی موبایل کنار عنوان دیده می‌شود؛ پانویس آیکن ندارد.'),
                ...LinkTargetFields::make(),
                Toggle::make(MenuItem::COLUMN_IS_ACTIVE)->label('فعال')->default(true)->inline(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable(MenuItem::COLUMN_SORT, fn (): bool => auth('admin')->user()?->can('Update:Menu') ?? false)
            ->defaultSort(MenuItem::COLUMN_SORT)
            ->paginated(false)
            ->description('ترتیب ردیف‌ها همان ترتیب منو است؛ با کشیدن جابه‌جا کنید. پیوندی که مقصدش حذف یا خاموش شده نمایش داده نمی‌شود.')
            ->columns([
                IconColumn::make(MenuItem::COLUMN_ICON)
                    ->label('آیکن')
                    ->icon(fn (?MenuIcon $state): ?string => $state === null ? null : 'heroicon-o-'.$state->value),
                TextColumn::make(MenuItem::COLUMN_TITLE)->label('عنوان'),
                TextColumn::make(MenuItem::COLUMN_LINK_TYPE)
                    ->label('مقصد')
                    ->state(fn (MenuItem $record): string => $record->getLinkType()->label())
                    ->description(fn (MenuItem $record): ?string => $record->getLinkType()->needsItem() || $record->opensInNewTab()
                        ? app(LinkUrls::class)->label($record->getLinkType(), $record->getLinkId(), $record->getExternalUrl())
                        : null),
                TextColumn::make('shown')
                    ->label('در سایت')
                    ->badge()
                    ->state(fn (MenuItem $record): string => match (true) {
                        ! $record->isActive() => 'خاموش',
                        $record->linkUrl() === null => 'مقصد در دسترس نیست',
                        default => 'نمایش داده می‌شود',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'نمایش داده می‌شود' => 'success',
                        'خاموش' => 'gray',
                        default => 'warning',
                    }),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('افزودن پیوند')
                    ->using(fn (array $data): MenuItem => $this->save($data)),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data): array => LinkTargetFields::withPickers($data))
                    ->using(fn (MenuItem $record, array $data): MenuItem => $this->save($data, $record)),
                DeleteAction::make()
                    ->using(function (MenuItem $record, DeleteMenuItem $deleteItem): bool {
                        $deleteItem($record);

                        return true;
                    }),
            ]);
    }

    /**
     * Drag and drop goes through ReorderMenuItems.
     *
     * @param  array<int|string>  $order
     */
    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        abort_unless(auth('admin')->user()?->can('Update:Menu') ?? false, 403);

        /** @var Menu $menu */
        $menu = $this->getOwnerRecord();
        app(ReorderMenuItems::class)($menu->getId(), array_values(array_map(intval(...), $order)));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function save(array $data, ?MenuItem $item = null): MenuItem
    {
        /** @var Menu $menu */
        $menu = $this->getOwnerRecord();

        return app(SaveMenuItem::class)(MenuItemData::fromArray([
            ...$data,
            MenuItem::COLUMN_MENU_ID => $menu->getId(),
            MenuItem::COLUMN_LINK_ID => LinkTargetFields::toLinkId($data),
        ]), $item);
    }

    /**
     * Each icon with its picture, for the picker.
     *
     * @return array<string, string>
     */
    private static function iconOptions(): array
    {
        $options = [];

        foreach (MenuIcon::cases() as $icon) {
            $options[$icon->value] = '<span style="display:inline-flex;align-items:center;gap:.5rem">'
                .svg('heroicon-o-'.$icon->value, '', ['style' => 'width:1.25rem;height:1.25rem'])->toHtml()
                .e($icon->label()).'</span>';
        }

        return $options;
    }
}
