<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Attributes;

use App\Modules\Catalog\Actions\DeleteAttribute;
use App\Modules\Catalog\Enums\AttributeType;
use App\Modules\Catalog\Exceptions\AttributeChangeRefused;
use App\Modules\Catalog\Filament\Resources\Attributes\Pages\CreateAttribute;
use App\Modules\Catalog\Filament\Resources\Attributes\Pages\EditAttribute;
use App\Modules\Catalog\Filament\Resources\Attributes\Pages\ListAttributes;
use App\Modules\Catalog\Filament\Resources\Attributes\RelationManagers\ValuesRelationManager;
use App\Modules\Catalog\Http\Requests\StoreAttributeRequest;
use App\Modules\Catalog\Models\Attribute;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * Attributes and their values (roadmap S11, D15.3): product details chosen from lists and used as filters.
 */
final class AttributeResource extends Resource
{
    protected static ?string $model = Attribute::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'کاتالوگ';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'ویژگی';

    protected static ?string $pluralModelLabel = 'ویژگی‌ها';

    protected static ?string $recordTitleAttribute = Attribute::COLUMN_NAME_FA;

    public static function form(Schema $schema): Schema
    {
        $rules = fn (?Attribute $record, string $field): array => StoreAttributeRequest::baseRules($record?->getId())[$field];

        return $schema
            ->columns(1)
            ->components([
                Section::make('مشخصات')
                    ->columns(2)
                    ->schema([
                        TextInput::make(Attribute::COLUMN_NAME_FA)
                            ->label('نام')
                            ->required()
                            ->rules(fn (?Attribute $record): array => $rules($record, Attribute::COLUMN_NAME_FA)),
                        TextInput::make(Attribute::COLUMN_CODE)
                            ->label('کد')
                            ->extraInputAttributes(['dir' => 'ltr'])
                            ->required()
                            ->helperText('نام لاتین ثابت برای فیلترها، مثلاً form یا skin_type (حروف کوچک، عدد و _).')
                            ->rules(fn (?Attribute $record): array => $rules($record, Attribute::COLUMN_CODE)),
                        Select::make(Attribute::COLUMN_TYPE)
                            ->label('نوع')
                            ->options(AttributeType::options())
                            ->default(AttributeType::Select->value)
                            ->required()
                            ->helperText('تک‌انتخابی: هر محصول یک مقدار (مثل شکل محصول). چندانتخابی: چند مقدار (مثل مناسب برای).'),
                        TextInput::make(Attribute::COLUMN_SORT)
                            ->label('ترتیب')
                            ->numeric()
                            ->rules(fn (?Attribute $record): array => $rules($record, Attribute::COLUMN_SORT)),
                        Toggle::make(Attribute::COLUMN_IS_FILTERABLE)
                            ->label('نمایش در فیلترها')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('values'))
            ->columns([
                TextColumn::make(Attribute::COLUMN_NAME_FA)->label('نام')->searchable()->sortable(),
                TextColumn::make(Attribute::COLUMN_CODE)->label('کد')->searchable(),
                TextColumn::make(Attribute::COLUMN_TYPE)
                    ->label('نوع')
                    ->badge()
                    ->formatStateUsing(fn (AttributeType $state): string => $state->label()),
                IconColumn::make(Attribute::COLUMN_IS_FILTERABLE)->label('فیلتر')->boolean(),
                TextColumn::make('values_count')->label('مقدارها')->numeric(),
            ])
            ->filters([TrashedFilter::make()])
            ->recordActions([EditAction::make(), self::deleteAction(), RestoreAction::make()])
            ->defaultSort(Attribute::COLUMN_SORT);
    }

    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->using(function (Attribute $record, DeleteAttribute $deleteAttribute, DeleteAction $action): bool {
                try {
                    $deleteAttribute($record);
                } catch (AttributeChangeRefused $refused) {
                    $action->failureNotificationTitle($refused->getMessage());

                    return false;
                }

                return true;
            });
    }

    /**
     * @return Builder<Model>
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getRelations(): array
    {
        return [ValuesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttributes::route('/'),
            'create' => CreateAttribute::route('/create'),
            'edit' => EditAttribute::route('/{record}/edit'),
        ];
    }
}
