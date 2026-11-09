<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Collections;

use App\Modules\Catalog\Actions\DeleteCollection;
use App\Modules\Catalog\Actions\SaveCollection;
use App\Modules\Catalog\Data\CollectionData;
use App\Modules\Catalog\Exceptions\CollectionChangeRefused;
use App\Modules\Catalog\Filament\Resources\Collections\Pages\CreateCollection;
use App\Modules\Catalog\Filament\Resources\Collections\Pages\EditCollection;
use App\Modules\Catalog\Filament\Resources\Collections\Pages\ListCollections;
use App\Modules\Catalog\Filament\Resources\Collections\RelationManagers\CollectionProductsRelationManager;
use App\Modules\Catalog\Http\Requests\StoreCollectionRequest;
use App\Modules\Catalog\Models\ProductCollection;
use App\Modules\Media\Filament\Forms\ImageUpload;
use App\Modules\Media\Filament\Tables\ImageThumbnail;
use App\Modules\Shared\Support\PersianDigits;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * Collections (roadmap S13b): hand-picked product lists in the admin's order, with an optional small icon and a
 * schedule. «محصولات محبوب» is the system collection shown in the empty search box.
 */
final class CollectionResource extends Resource
{
    protected static ?string $model = ProductCollection::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'کاتالوگ';

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'کالکشن';

    protected static ?string $pluralModelLabel = 'کالکشن‌ها';

    protected static ?string $recordTitleAttribute = ProductCollection::COLUMN_NAME_FA;

    public static function form(Schema $schema): Schema
    {
        $rules = fn (?ProductCollection $record, string $field): array => StoreCollectionRequest::baseRules($record?->getId())[$field];

        return $schema
            ->columns(1)
            ->components([
                Section::make('مشخصات')
                    ->columns(2)
                    ->schema([
                        TextInput::make(ProductCollection::COLUMN_NAME_FA)
                            ->label('نام فارسی')
                            ->required()
                            ->rules(fn (?ProductCollection $record): array => $rules($record, ProductCollection::COLUMN_NAME_FA)),
                        TextInput::make(ProductCollection::COLUMN_NAME_EN)
                            ->label('نام انگلیسی')
                            ->extraInputAttributes(['dir' => 'ltr'])
                            ->rules(fn (?ProductCollection $record): array => $rules($record, ProductCollection::COLUMN_NAME_EN)),
                        TextInput::make(ProductCollection::COLUMN_SLUG)
                            ->label('نامک (آدرس)')
                            ->prefix('/collections/')
                            ->extraInputAttributes(['dir' => 'ltr'])
                            ->disabled(fn (?ProductCollection $record): bool => $record?->isSystem() ?? false)
                            ->rules(fn (?ProductCollection $record): array => $rules($record, ProductCollection::COLUMN_SLUG))
                            ->helperText(fn (?ProductCollection $record): string => match (true) {
                                $record?->isSystem() ?? false => 'نامک کالکشن سیستمی ثابت است.',
                                $record instanceof ProductCollection => 'تغییر نامک، پیوندهای قبلی این کالکشن را از کار می‌اندازد.',
                                default => 'خالی بگذارید تا از نام انگلیسی (یا فارسی) ساخته شود.',
                            }),
                        TextInput::make(ProductCollection::COLUMN_SORT)
                            ->label('ترتیب در فهرست کالکشن‌ها')
                            ->numeric()
                            ->default(0)
                            ->rules(fn (?ProductCollection $record): array => $rules($record, ProductCollection::COLUMN_SORT)),
                        Textarea::make(ProductCollection::COLUMN_DESCRIPTION)
                            ->label('توضیح کوتاه')
                            ->rows(2)
                            ->columnSpanFull()
                            ->rules(fn (?ProductCollection $record): array => $rules($record, ProductCollection::COLUMN_DESCRIPTION)),
                    ]),
                Section::make('نمایش')
                    ->columns(3)
                    ->schema([
                        Toggle::make(ProductCollection::COLUMN_IS_ACTIVE)
                            ->label('فعال')
                            ->default(true)
                            ->inline(false),
                        DateTimePicker::make(ProductCollection::COLUMN_STARTS_AT)
                            ->label('شروع نمایش')
                            ->helperText('خالی یعنی از همین حالا.'),
                        DateTimePicker::make(ProductCollection::COLUMN_ENDS_AT)
                            ->label('پایان نمایش')
                            ->helperText('خالی یعنی بدون پایان.')
                            ->rules(fn (?ProductCollection $record): array => $rules($record, ProductCollection::COLUMN_ENDS_AT)),
                    ]),
                Section::make('آیکن')
                    ->description('آیکن کوچکی که کنار نام کالکشن نمایش داده می‌شود (اختیاری).')
                    ->schema([
                        ImageUpload::make(ProductCollection::IMAGE_ICON, 'category_icon')->label('آیکن'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('products'))
            ->columns([
                ImageThumbnail::make(ProductCollection::IMAGE_ICON, 'category_icon'),
                TextColumn::make(ProductCollection::COLUMN_NAME_FA)
                    ->label('نام')
                    ->description(fn (ProductCollection $record): ?string => $record->isSystem() ? 'سیستمی: جعبه جستجوی خالی' : $record->getNameEn())
                    ->searchable([ProductCollection::COLUMN_NAME_FA, ProductCollection::COLUMN_NAME_EN])
                    ->sortable(),
                TextColumn::make('products_count')->label('محصولات')->numeric(),
                ToggleColumn::make(ProductCollection::COLUMN_IS_ACTIVE)
                    ->label('فعال')
                    ->disabled(fn (ProductCollection $record): bool => $record->trashed() || ! (auth('admin')->user()?->can('update', $record) ?? false))
                    ->updateStateUsing(fn (ProductCollection $record, bool $state, SaveCollection $saveCollection): bool => $saveCollection(
                        CollectionData::fromArray([...$record->attributesToArray(), ProductCollection::COLUMN_IS_ACTIVE => $state]),
                        $record,
                    )->isActive()),
                IconColumn::make('scheduled')
                    ->label('زمان‌بندی')
                    // A clock when a schedule is set; nothing otherwise (a false state would show a red cross).
                    ->state(fn (ProductCollection $record): ?bool => $record->getStartsAt() instanceof Carbon || $record->getEndsAt() instanceof Carbon ? true : null)
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedClock)
                    ->trueColor('gray'),
                TextColumn::make(ProductCollection::COLUMN_SORT)->label('ترتیب')->sortable(),
            ])
            ->filters([TrashedFilter::make()])
            ->recordActions([EditAction::make(), self::deleteAction(), RestoreAction::make()])
            ->defaultSort(ProductCollection::COLUMN_SORT);
    }

    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->visible(fn (ProductCollection $record): bool => ! $record->isSystem() && ! $record->trashed())
            ->modalDescription(fn (ProductCollection $record): string => 'این کالکشن با '.PersianDigits::toPersian($record->products()->count()).' محصول به سطل زباله می‌رود؛ خود محصولات حذف نمی‌شوند.')
            ->using(function (ProductCollection $record, DeleteCollection $deleteCollection, Action $action): bool {
                try {
                    $deleteCollection($record);
                } catch (CollectionChangeRefused $refused) {
                    $action->failureNotificationTitle($refused->getMessage());

                    return false;
                }

                return true;
            });
    }

    public static function getRelations(): array
    {
        return [CollectionProductsRelationManager::class];
    }

    /**
     * @return Builder<Model>
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCollections::route('/'),
            'create' => CreateCollection::route('/create'),
            'edit' => EditCollection::route('/{record}/edit'),
        ];
    }
}
