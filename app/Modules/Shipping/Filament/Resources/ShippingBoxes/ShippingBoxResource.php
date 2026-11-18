<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Filament\Resources\ShippingBoxes;

use App\Modules\Shared\Support\PersianDigits;
use App\Modules\Shipping\Actions\SaveShippingBox;
use App\Modules\Shipping\Data\ShippingBoxData;
use App\Modules\Shipping\Filament\Clusters\Packing;
use App\Modules\Shipping\Filament\Resources\ShippingBoxes\Pages\ManageShippingBoxes;
use App\Modules\Shipping\Http\Requests\SaveShippingBoxRequest;
use App\Modules\Shipping\Models\ShippingBox;
use App\Modules\Shipping\Packing\BoxPacker;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * «کارتن‌ها» (roadmap S31, owner 2026-11-17): the boxes we pack in, smallest first, with inner sizes, their own
 * weight and the most a parcel in each may weigh. Seeded with Tipax's standard cartons 1–11; boxes we do not stock are switched off, never deleted.
 */
final class ShippingBoxResource extends Resource
{
    protected static ?string $model = ShippingBox::class;

    protected static ?string $cluster = Packing::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'کارتن';

    protected static ?string $pluralModelLabel = 'کارتن‌ها';

    public static function form(Schema $schema): Schema
    {
        $rules = SaveShippingBoxRequest::baseRules();
        $side = fn (string $column, string $label): TextInput => TextInput::make($column)->label($label)->numeric()->integer()->suffix('سانتی‌متر')->required()->rules($rules[$column]);

        return $schema
            ->columns(3)
            ->components([
                TextInput::make(ShippingBox::COLUMN_NAME)->label('نام')->required()->maxLength(60)->rules($rules[ShippingBox::COLUMN_NAME]),
                TextInput::make(ShippingBox::COLUMN_TIPAX_SIZE)
                    ->label('شماره کارتن تیپاکس')
                    ->numeric()
                    ->integer()
                    ->helperText('اگر یکی از کارتن‌های استاندارد تیپاکس است (۱ تا ۱۱).')
                    ->rules($rules[ShippingBox::COLUMN_TIPAX_SIZE]),
                Toggle::make(ShippingBox::COLUMN_IS_ACTIVE)->label('در دسترس')->helperText('کارتنی که نداریم را خاموش کنید.')->default(true)->inline(false),
                $side(ShippingBox::COLUMN_LENGTH_CM, 'طول داخلی'),
                $side(ShippingBox::COLUMN_WIDTH_CM, 'عرض داخلی'),
                $side(ShippingBox::COLUMN_HEIGHT_CM, 'ارتفاع داخلی'),
                TextInput::make(ShippingBox::COLUMN_EMPTY_WEIGHT_G)
                    ->label('وزن خود کارتن')
                    ->numeric()
                    ->integer()
                    ->suffix('گرم')
                    ->required()
                    ->helperText('با پرکننده (نایلون حباب‌دار، چسب). در وزن بسته حساب می‌شود.')
                    ->rules($rules[ShippingBox::COLUMN_EMPTY_WEIGHT_G]),
                TextInput::make(ShippingBox::COLUMN_MAX_WEIGHT_G)
                    ->label('حداکثر وزن بسته')
                    ->numeric()
                    ->integer()
                    ->suffix('گرم')
                    ->required()
                    ->default(BoxPacker::MAX_PARCEL_WEIGHT_G)
                    ->helperText('بیشترین وزنی که این کارتن تحمل می‌کند، با وزن خود کارتن؛ حداکثر '.PersianDigits::toPersian(BoxPacker::MAX_PARCEL_WEIGHT_G / 1000).' کیلوگرم (سقف تاپین). کارتن با رسیدن به این وزن یا پر شدن فضایش، هر کدام زودتر، بسته می‌شود.')
                    // Filament resolves the other field's path for «more than the box's own weight».
                    ->gt(ShippingBox::COLUMN_EMPTY_WEIGHT_G)
                    ->rules(array_values(array_filter($rules[ShippingBox::COLUMN_MAX_WEIGHT_G], fn (string $rule): bool => ! str_starts_with($rule, 'gt:')))),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort(ShippingBox::COLUMN_SORT)
            ->paginated(false)
            ->description('از کوچک به بزرگ. بسته‌بندی کوچک‌ترین کارتنی را برمی‌دارد که همه کالاها در آن جا شوند و وزنش از حداکثر همان کارتن بیشتر نشود.')
            ->columns([
                TextColumn::make(ShippingBox::COLUMN_NAME)->label('نام'),
                TextColumn::make('sizes')
                    ->label('ابعاد داخلی (سانتی‌متر)')
                    ->state(fn (ShippingBox $record): string => PersianDigits::toPersian($record->sizesLabel())),
                TextColumn::make(ShippingBox::COLUMN_EMPTY_WEIGHT_G)
                    ->label('وزن کارتن')
                    ->formatStateUsing(fn (int $state): string => PersianDigits::toPersian($state).' گرم'),
                TextColumn::make(ShippingBox::COLUMN_MAX_WEIGHT_G)
                    ->label('حداکثر وزن بسته')
                    ->formatStateUsing(fn (int $state): string => PersianDigits::toPersian(rtrim(rtrim(number_format($state / 1000, 1), '0'), '.')).' کیلوگرم'),
                IconColumn::make(ShippingBox::COLUMN_IS_ACTIVE)->label('در دسترس')->boolean(),
            ])
            ->recordActions([
                EditAction::make()->using(fn (ShippingBox $record, array $data): ShippingBox => app(SaveShippingBox::class)(ShippingBoxData::fromArray($data), $record)),
            ]);
    }

    public static function createAction(): CreateAction
    {
        return CreateAction::make()
            ->label('افزودن کارتن')
            ->using(fn (array $data): ShippingBox => app(SaveShippingBox::class)(ShippingBoxData::fromArray($data)));
    }

    public static function getPages(): array
    {
        return ['index' => ManageShippingBoxes::route('/')];
    }
}
