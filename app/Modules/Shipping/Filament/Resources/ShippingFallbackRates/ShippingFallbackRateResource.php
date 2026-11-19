<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Filament\Resources\ShippingFallbackRates;

use App\Modules\Shared\Support\PersianDigits;
use App\Modules\Shipping\Actions\SaveShippingFallbackRate;
use App\Modules\Shipping\Data\ShippingFallbackRateData;
use App\Modules\Shipping\Filament\Resources\ShippingFallbackRates\Pages\ManageShippingFallbackRates;
use App\Modules\Shipping\Http\Requests\SaveShippingFallbackRateRequest;
use App\Modules\Shipping\Models\ShippingFallbackRate;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * «نرخ ارسال جایگزین» under «ارسال» (roadmap S31, owner 2026-11-19): what shipping costs when Tipax cannot price an
 * order, per weight band and distance from the warehouse. Prices are typed in toman and kept in rial.
 */
final class ShippingFallbackRateResource extends Resource
{
    protected static ?string $model = ShippingFallbackRate::class;

    protected static string|UnitEnum|null $navigationGroup = 'ارسال';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'نرخ ارسال جایگزین';

    protected static ?string $modelLabel = 'نرخ ارسال';

    protected static ?string $pluralModelLabel = 'نرخ‌های ارسال جایگزین';

    public static function form(Schema $schema): Schema
    {
        $rules = SaveShippingFallbackRateRequest::baseRules();
        $price = fn (string $column, string $label): TextInput => TextInput::make($column)->label($label)->toman()->required(); // @phpstan-ignore method.notFound (macro, AdminPanelProvider)

        return $schema
            ->columns(2)
            ->components([
                TextInput::make(ShippingFallbackRate::COLUMN_MAX_WEIGHT_G)
                    ->label('تا وزن')
                    ->numeric()
                    ->integer()
                    ->suffix('گرم')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->helperText('بسته‌ای تا این وزن (با وزن کارتن) قیمت همین ردیف را می‌گیرد؛ سنگین‌تر از همه ردیف‌ها، قیمت سنگین‌ترین ردیف.')
                    ->rules($rules[ShippingFallbackRate::COLUMN_MAX_WEIGHT_G])
                    ->columnSpanFull(),
                $price(ShippingFallbackRate::COLUMN_SAME_CITY_PRICE, 'درون شهر'),
                $price(ShippingFallbackRate::COLUMN_SAME_PROVINCE_PRICE, 'درون استان'),
                $price(ShippingFallbackRate::COLUMN_OTHER_PROVINCE_PRICE, 'استان دیگر'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort(ShippingFallbackRate::COLUMN_MAX_WEIGHT_G)
            ->paginated(false)
            ->description('وقتی تیپاکس قیمت ندهد (پاسخ ندهد یا شهر مبدأ انتخاب نشده باشد)، هزینه هر بسته از این جدول و بر اساس وزن و فاصله تا انبار حساب می‌شود. جدول خالی یعنی «هزینه ارسال ثابت» تنظیمات فروشگاه.')
            ->columns([
                TextColumn::make(ShippingFallbackRate::COLUMN_MAX_WEIGHT_G)
                    ->label('تا وزن')
                    ->formatStateUsing(fn (int $state): string => PersianDigits::toPersian(rtrim(rtrim(number_format($state / 1000, 1), '0'), '.')).' کیلوگرم'),
                TextColumn::make(ShippingFallbackRate::COLUMN_SAME_CITY_PRICE)->label('درون شهر')->toman(), // @phpstan-ignore method.notFound (macro)
                TextColumn::make(ShippingFallbackRate::COLUMN_SAME_PROVINCE_PRICE)->label('درون استان')->toman(), // @phpstan-ignore method.notFound (macro)
                TextColumn::make(ShippingFallbackRate::COLUMN_OTHER_PROVINCE_PRICE)->label('استان دیگر')->toman(), // @phpstan-ignore method.notFound (macro)
            ])
            ->recordActions([
                EditAction::make()->using(fn (ShippingFallbackRate $record, array $data): ShippingFallbackRate => app(SaveShippingFallbackRate::class)(ShippingFallbackRateData::fromArray($data), $record)),
                DeleteAction::make(),
            ]);
    }

    public static function createAction(): CreateAction
    {
        return CreateAction::make()
            ->label('افزودن ردیف')
            ->using(fn (array $data): ShippingFallbackRate => app(SaveShippingFallbackRate::class)(ShippingFallbackRateData::fromArray($data)));
    }

    public static function getPages(): array
    {
        return ['index' => ManageShippingFallbackRates::route('/')];
    }
}
