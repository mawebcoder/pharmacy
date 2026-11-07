<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Tags;

use App\Modules\Catalog\Actions\DeleteTag;
use App\Modules\Catalog\Actions\SaveTag;
use App\Modules\Catalog\Data\TagData;
use App\Modules\Catalog\Enums\TagType;
use App\Modules\Catalog\Filament\Resources\Tags\Pages\CreateTag;
use App\Modules\Catalog\Filament\Resources\Tags\Pages\EditTag;
use App\Modules\Catalog\Filament\Resources\Tags\Pages\ListTags;
use App\Modules\Catalog\Http\Requests\StoreTagRequest;
use App\Modules\Catalog\Models\Tag;
use App\Modules\Media\Filament\Forms\ImageUpload;
use App\Modules\Media\Filament\Tables\ImageThumbnail;
use App\Modules\Shared\Support\PersianDigits;
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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * Tags (roadmap S11, D15.3): nutrient and need shortcuts shown as chips; featured ones appear on the home page.
 */
final class TagResource extends Resource
{
    protected static ?string $model = Tag::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHashtag;

    protected static string|UnitEnum|null $navigationGroup = 'کاتالوگ';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'برچسب';

    protected static ?string $pluralModelLabel = 'برچسب‌ها';

    protected static ?string $recordTitleAttribute = Tag::COLUMN_NAME_FA;

    public static function form(Schema $schema): Schema
    {
        $rules = fn (?Tag $record, string $field): array => StoreTagRequest::baseRules($record?->getId())[$field];

        return $schema
            ->columns(1)
            ->components([
                Section::make('مشخصات')
                    ->columns(2)
                    ->schema([
                        TextInput::make(Tag::COLUMN_NAME_FA)
                            ->label('نام فارسی')
                            ->required()
                            ->rules(fn (?Tag $record): array => $rules($record, Tag::COLUMN_NAME_FA)),
                        TextInput::make(Tag::COLUMN_NAME_EN)
                            ->label('نام انگلیسی')
                            ->extraInputAttributes(['dir' => 'ltr'])
                            ->rules(fn (?Tag $record): array => $rules($record, Tag::COLUMN_NAME_EN)),
                        Select::make(Tag::COLUMN_TYPE)
                            ->label('نوع')
                            ->options(TagType::options())
                            ->default(TagType::Nutrient->value)
                            ->required(),
                        TextInput::make(Tag::COLUMN_SLUG)
                            ->label('نامک (آدرس)')
                            ->prefix('/t/')
                            ->extraInputAttributes(['dir' => 'ltr'])
                            ->rules(fn (?Tag $record): array => $rules($record, Tag::COLUMN_SLUG))
                            ->helperText(fn (?Tag $record): string => $record instanceof Tag
                                ? 'تغییر نامک، پیوندهای قبلی این برچسب را از کار می‌اندازد.'
                                : 'خالی بگذارید تا از نام انگلیسی (یا فارسی) ساخته شود.'),
                        Toggle::make(Tag::COLUMN_IS_FEATURED)
                            ->label('نمایش در صفحه اصلی')
                            ->helperText('برچسب‌های ویژه به ترتیب «ترتیب» در صفحه اصلی نمایش داده می‌شوند (۸ تا ۱۲ برچسب).'),
                        TextInput::make(Tag::COLUMN_SORT)
                            ->label('ترتیب')
                            ->numeric()
                            ->default(0)
                            ->rules(fn (?Tag $record): array => $rules($record, Tag::COLUMN_SORT)),
                    ]),
                Section::make('آیکن')
                    ->schema([
                        ImageUpload::make(Tag::IMAGE_ICON, 'category_icon')->label('آیکن'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('products'))
            ->columns([
                ImageThumbnail::make(Tag::IMAGE_ICON, 'category_icon'),
                TextColumn::make(Tag::COLUMN_NAME_FA)
                    ->label('نام')
                    ->description(fn (Tag $record): ?string => $record->getNameEn())
                    ->searchable([Tag::COLUMN_NAME_FA, Tag::COLUMN_NAME_EN])
                    ->sortable(),
                TextColumn::make(Tag::COLUMN_TYPE)
                    ->label('نوع')
                    ->badge()
                    ->formatStateUsing(fn (TagType $state): string => $state->label())
                    ->color(fn (TagType $state): string => $state->color()),
                ToggleColumn::make(Tag::COLUMN_IS_FEATURED)
                    ->label('صفحه اصلی')
                    ->disabled(fn (Tag $record): bool => $record->trashed() || ! (auth('admin')->user()?->can('update', $record) ?? false))
                    ->updateStateUsing(fn (Tag $record, bool $state, SaveTag $saveTag): bool => $saveTag(
                        TagData::fromArray([...$record->attributesToArray(), Tag::COLUMN_IS_FEATURED => $state]),
                        $record,
                    )->isFeatured()),
                TextColumn::make(Tag::COLUMN_SORT)->label('ترتیب')->sortable(),
                TextColumn::make('products_count')->label('محصولات')->numeric(),
            ])
            ->filters([
                SelectFilter::make(Tag::COLUMN_TYPE)->label('نوع')->options(TagType::options()),
                TernaryFilter::make(Tag::COLUMN_IS_FEATURED)->label('صفحه اصلی'),
                TrashedFilter::make(),
            ])
            ->recordActions([EditAction::make(), self::deleteAction(), RestoreAction::make()])
            ->defaultSort(Tag::COLUMN_SORT);
    }

    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->modalDescription(fn (Tag $record): string => 'این برچسب از '.PersianDigits::toPersian($record->products()->count()).' محصول برداشته می‌شود و به سطل زباله می‌رود.')
            ->using(function (Tag $record, DeleteTag $deleteTag): bool {
                $deleteTag($record);

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

    public static function getPages(): array
    {
        return [
            'index' => ListTags::route('/'),
            'create' => CreateTag::route('/create'),
            'edit' => EditTag::route('/{record}/edit'),
        ];
    }
}
