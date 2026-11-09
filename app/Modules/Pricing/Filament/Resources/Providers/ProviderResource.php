<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Filament\Resources\Providers;

use App\Modules\Locations\Queries\CitySearch;
use App\Modules\Pricing\Filament\Resources\Providers\Pages\CreateProvider;
use App\Modules\Pricing\Filament\Resources\Providers\Pages\EditProvider;
use App\Modules\Pricing\Filament\Resources\Providers\Pages\ListProviders;
use App\Modules\Pricing\Filament\Resources\Providers\Pages\ViewProvider;
use App\Modules\Pricing\Filament\Resources\Providers\RelationManagers\ProviderPricesRelationManager;
use App\Modules\Pricing\Http\Requests\StoreProviderRequest;
use App\Modules\Pricing\Models\Provider;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Suppliers («تأمین‌کنندگان», roadmap D3.2, S14): who we buy from. What each one charges per SKU is set on each SKU's
 * suppliers page and on the supplier's view page (its SKUs table). Switching a supplier off ignores all its prices (the SKUs are re-priced). Suppliers are
 * switched off rather than deleted, so price records keep them.
 */
final class ProviderResource extends Resource
{
    protected static ?string $model = Provider::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'قیمت‌گذاری';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'تأمین‌کننده';

    protected static ?string $pluralModelLabel = 'تأمین‌کنندگان';

    protected static ?string $recordTitleAttribute = Provider::COLUMN_NAME;

    public static function form(Schema $schema): Schema
    {
        $rules = StoreProviderRequest::baseRules();

        return $schema
            ->columns(1)
            ->components([
                Section::make('مشخصات')
                    ->columns(2)
                    ->schema([
                        TextInput::make(Provider::COLUMN_NAME)->label('نام')->required()->rules($rules[Provider::COLUMN_NAME]),
                        Toggle::make(Provider::COLUMN_IS_ACTIVE)
                            ->label('فعال')
                            ->default(true)
                            ->inline(false)
                            ->helperText('خاموش: قیمت‌های این تأمین‌کننده در همه SKUها نادیده گرفته می‌شود و قیمت‌ها دوباره محاسبه می‌شوند.'),
                        TextInput::make(Provider::COLUMN_CONTACT_NAME)->label('نام رابط')->rules($rules[Provider::COLUMN_CONTACT_NAME]),
                        TextInput::make(Provider::COLUMN_PHONE)->label('تلفن')->tel()->telRegex('/^[0-9۰-۹٠-٩+\-\s]+$/u')->extraInputAttributes(['dir' => 'ltr'])->rules($rules[Provider::COLUMN_PHONE]),
                        Select::make(Provider::COLUMN_CITY_ID)
                            ->label('شهر')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => app(CitySearch::class)->options($search))
                            ->getOptionLabelUsing(fn (int|string $value): ?string => app(CitySearch::class)->label((int) $value))
                            ->rules($rules[Provider::COLUMN_CITY_ID]),
                        TextInput::make(Provider::COLUMN_PAYMENT_TERMS)->label('شرایط پرداخت')->placeholder('مثلاً چک ۶۰ روزه')->rules($rules[Provider::COLUMN_PAYMENT_TERMS]),
                        Textarea::make(Provider::COLUMN_ADDRESS)->label('نشانی')->rows(2)->columnSpanFull()->rules($rules[Provider::COLUMN_ADDRESS]),
                        Textarea::make(Provider::COLUMN_NOTES)->label('یادداشت')->rows(3)->columnSpanFull()->rules($rules[Provider::COLUMN_NOTES]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('prices'))
            ->defaultSort(Provider::COLUMN_NAME)
            ->columns([
                TextColumn::make(Provider::COLUMN_NAME)->label('نام')->searchable()->sortable(),
                TextColumn::make(Provider::COLUMN_CONTACT_NAME)->label('رابط')->placeholder('—'),
                TextColumn::make(Provider::COLUMN_PHONE)->label('تلفن')->placeholder('—'),
                TextColumn::make('prices_count')->label('تعداد SKU')->sortable(),
                IconColumn::make(Provider::COLUMN_IS_ACTIVE)->label('فعال')->boolean(),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProviders::route('/'),
            'create' => CreateProvider::route('/create'),
            'view' => ViewProvider::route('/{record}'),
            'edit' => EditProvider::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [ProviderPricesRelationManager::class];
    }
}
