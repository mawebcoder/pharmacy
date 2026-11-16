<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\FlashDeals;

use App\Modules\Catalog\Actions\DeleteFlashDeal;
use App\Modules\Catalog\Exceptions\FlashDealRefused;
use App\Modules\Catalog\Filament\Forms\ImageSelect;
use App\Modules\Catalog\Filament\Resources\FlashDeals\Pages\CreateFlashDeal;
use App\Modules\Catalog\Filament\Resources\FlashDeals\Pages\EditFlashDeal;
use App\Modules\Catalog\Filament\Resources\FlashDeals\Pages\ListFlashDeals;
use App\Modules\Catalog\Http\Requests\StoreFlashDealRequest;
use App\Modules\Catalog\Models\FlashDeal;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Pricing\Queries\CurrentPriceRecords;
use App\Modules\Pricing\Queries\SupplierOptions;
use App\Modules\Shared\Support\JalaliDate;
use App\Modules\Shared\Support\PersianDigits;
use App\Modules\Shared\Support\Toman;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * «پیشنهاد لحظه‌ای» (roadmap S33; in «فروش», owner 2026-11-17): a SKU at a deal price between two times, optionally for a limited number
 * of units. While it runs and has units left, the SKU sells at the deal price everywhere; then it goes back to its
 * regular price on its own.
 */
final class FlashDealResource extends Resource
{
    protected static ?string $model = FlashDeal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static string|UnitEnum|null $navigationGroup = 'فروش';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'پیشنهاد لحظه‌ای';

    protected static ?string $pluralModelLabel = 'پیشنهادهای لحظه‌ای';

    public static function form(Schema $schema): Schema
    {
        $rules = StoreFlashDealRequest::baseRules();

        return $schema->components([
            Section::make('پیشنهاد')
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    ImageSelect::skus(Select::make(FlashDeal::COLUMN_VARIANT_ID))
                        ->label('تنوع (SKU)')
                        ->required()
                        ->live()
                        // The supplier the site sells this SKU from now (S15b) unless the admin picks another.
                        ->afterStateUpdated(fn (Set $set, mixed $state) => $set(FlashDeal::COLUMN_PROVIDER_ID, blank($state) ? null : self::sellingSupplierId((int) $state)))
                        ->rules($rules[FlashDeal::COLUMN_VARIANT_ID])
                        ->columnSpanFull(),
                    // The SKU's prices now, to set the deal price against (owner, 2026-11-17).
                    Grid::make(['default' => 2, 'md' => 4])
                        ->columnSpanFull()
                        ->visible(fn (Get $get): bool => self::variant($get) instanceof ProductVariant)
                        ->schema([
                            self::price('official_price', 'قیمت روی جعبه', fn (ProductVariant $variant): ?int => $variant->getOfficialPrice()),
                            self::price('list_price', 'قیمت فروش', fn (ProductVariant $variant): int => $variant->getPrice()),
                            self::price('haraj_price', 'قیمت حراج', fn (ProductVariant $variant): ?int => $variant->getSalePrice()),
                            self::price('current_price', 'قیمت فعلی سایت', fn (ProductVariant $variant): int => $variant->currentPrice())
                                ->helperText(fn (Get $get): ?string => self::liveDealNote($get))
                                ->color(fn (Get $get): ?string => self::liveDealNote($get) === null ? null : 'warning'),
                        ]),
                    Select::make(FlashDeal::COLUMN_PROVIDER_ID)
                        ->label('تأمین‌کننده')
                        ->options(fn (Get $get): array => self::supplierOptions($get))
                        ->required()
                        ->rules($rules[FlashDeal::COLUMN_PROVIDER_ID])
                        ->live()
                        ->helperText('برای هر پیشنهاد جداگانه انتخاب می‌شود (مثلاً تأمین‌کننده‌ای که تخفیف ویژه داده یا کالایش نزدیک انقضاست)؛ سفارش‌های این پیشنهاد از او ثبت می‌شوند.')
                        ->columnSpanFull(),
                    TextInput::make(FlashDeal::COLUMN_DEAL_PRICE)
                        ->label('قیمت پیشنهاد')
                        ->toman() // @phpstan-ignore method.notFound (macro, AdminPanelProvider)
                        ->required()
                        ->live(onBlur: true)
                        ->helperText(fn (Get $get): string => self::variant($get) instanceof ProductVariant
                            ? 'قیمت فعلی این تنوع: '.Toman::format(self::variant($get)->currentPrice()).'؛ قیمت پیشنهاد باید کمتر از قیمت عادی ('.Toman::format(self::variant($get)->regularPrice()).') باشد.'
                            : 'اول تنوع را انتخاب کنید؛ قیمت پیشنهاد باید کمتر از قیمت فعلی آن باشد.'),
                    TextInput::make(FlashDeal::COLUMN_QUANTITY_LIMIT)
                        ->label('سقف تعداد')
                        ->integer()
                        ->minValue(1)
                        ->helperText('خالی یعنی بدون سقف. با رسیدن به سقف، قیمت عادی برمی‌گردد.'),
                    DateTimePicker::make(FlashDeal::COLUMN_STARTS_AT)
                        ->label('شروع')
                        ->jalali() // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
                        ->required(),
                    DateTimePicker::make(FlashDeal::COLUMN_ENDS_AT)
                        ->label('پایان')
                        ->jalali() // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
                        ->required()
                        ->after(FlashDeal::COLUMN_STARTS_AT),
                    Toggle::make(FlashDeal::COLUMN_IS_ACTIVE)->label('فعال')->default(true),
                    Callout::make('بدون سود')
                        ->key('loss_warning')
                        ->warning()
                        ->columnSpanFull()
                        ->description(fn (Get $get): string => 'قیمت پیشنهاد برابر یا کمتر از قیمت خرید این تأمین‌کننده ('.Toman::format((int) self::supplierCost($get)).') است.')
                        ->visible(fn (Get $get): bool => self::atLoss($get)),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('variant.product'))
            ->defaultSort(FlashDeal::COLUMN_STARTS_AT, 'desc')
            ->columns([
                TextColumn::make('product')
                    ->label('محصول')
                    ->state(fn (FlashDeal $record): string => (string) $record->getVariant()?->getProduct()?->getNameFa())
                    ->description(fn (FlashDeal $record): string => trim(($record->getVariant()?->getTitleFa() ?? '').' · '.($record->getVariant()?->getSku() ?? ''), ' ·'))
                    ->wrap(),
                TextColumn::make(FlashDeal::COLUMN_DEAL_PRICE)
                    ->label('قیمت پیشنهاد')
                    ->formatStateUsing(fn (int $state): string => Toman::format($state))
                    ->description(fn (FlashDeal $record): ?string => $record->getVariant() === null ? null : 'عادی: '.Toman::format($record->getVariant()->regularPrice())),
                TextColumn::make(FlashDeal::COLUMN_STARTS_AT)
                    ->label('از')
                    ->jalaliDateTime() // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
                    ->sortable(),
                TextColumn::make(FlashDeal::COLUMN_ENDS_AT)
                    ->label('تا')
                    ->jalaliDateTime(), // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
                TextColumn::make(FlashDeal::COLUMN_SOLD_COUNT)
                    ->label('فروش')
                    ->formatStateUsing(fn (int $state, FlashDeal $record): string => PersianDigits::toPersian($state).($record->getQuantityLimit() === null ? '' : ' از '.PersianDigits::toPersian($record->getQuantityLimit()))),
                TextColumn::make('state')
                    ->label('وضعیت')
                    ->badge()
                    ->state(fn (FlashDeal $record): string => self::state($record)[0])
                    ->color(fn (FlashDeal $record): string => self::state($record)[1]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->using(function (FlashDeal $record, DeleteFlashDeal $delete): bool {
                        try {
                            $delete($record);
                        } catch (FlashDealRefused $refused) {
                            Notification::make()->danger()->title($refused->getMessage())->send();

                            return false;
                        }

                        return true;
                    }),
            ]);
    }

    /**
     * The SKU's suppliers with their cost, the one the site sells from now marked «تأمین‌کننده فعلی سایت».
     *
     * @return array<int, string>
     */
    private static function supplierOptions(Get $get): array
    {
        $variantId = $get(FlashDeal::COLUMN_VARIANT_ID);

        if (blank($variantId)) {
            return [];
        }

        $options = app(SupplierOptions::class)->forVariant((int) $variantId);
        $current = self::sellingSupplierId((int) $variantId);

        if ($current !== null && isset($options[$current])) {
            $options[$current] .= ' (تأمین‌کننده فعلی سایت)';
        }

        return $options;
    }

    /**
     * The supplier the site sells the SKU from now (its latest price record, S15b); null at the printed price.
     */
    private static function sellingSupplierId(int $variantId): ?int
    {
        return app(CurrentPriceRecords::class)->for([$variantId])[$variantId]->providerId ?? null;
    }

    private static function variant(Get $get): ?ProductVariant
    {
        $id = $get(FlashDeal::COLUMN_VARIANT_ID);

        return blank($id) ? null : ProductVariant::query()->find((int) $id);
    }

    /**
     * @param  Closure(ProductVariant): ?int  $amount
     */
    private static function price(string $name, string $label, Closure $amount): TextEntry
    {
        return TextEntry::make($name)
            ->label($label)
            ->state(function (Get $get) use ($amount): string {
                $variant = self::variant($get);
                $value = $variant instanceof ProductVariant ? $amount($variant) : null;

                return $value === null ? '—' : Toman::format($value);
            });
    }

    /**
     * «با پیشنهاد لحظه‌ای فعال تا …» when the SKU's current price is a live deal's price, so it is not read as the
     * regular price.
     */
    private static function liveDealNote(Get $get): ?string
    {
        $variant = self::variant($get);
        $deal = $variant?->getFlashDealId() === null ? null : FlashDeal::query()->find($variant->getFlashDealId());

        return $deal instanceof FlashDeal
            ? 'با پیشنهاد لحظه‌ای فعال تا '.JalaliDate::display($deal->getEndsAt(), 'j F، H:i').'؛ قیمت عادی: '.Toman::format($variant->regularPrice())
            : null;
    }

    /**
     * The chosen supplier's cost of the SKU, or the usual one's when none is chosen (rial).
     */
    private static function supplierCost(Get $get): ?int
    {
        $variant = self::variant($get);

        if (! $variant instanceof ProductVariant) {
            return null;
        }

        $providerId = $get(FlashDeal::COLUMN_PROVIDER_ID);

        return blank($providerId)
            ? app(CurrentPriceRecords::class)->for([$variant->getId()])[$variant->getId()]->providerCost ?? null
            : app(SupplierOptions::class)->cost((int) $providerId, $variant->getId());
    }

    private static function atLoss(Get $get): bool
    {
        $typed = Toman::rialFromInput(is_scalar($get(FlashDeal::COLUMN_DEAL_PRICE)) ? (string) $get(FlashDeal::COLUMN_DEAL_PRICE) : null);
        $cost = self::supplierCost($get);

        return $typed !== null && $cost !== null && $typed <= $cost;
    }

    /**
     * @return array{0: string, 1: string} label and badge colour
     */
    public static function state(FlashDeal $deal): array
    {
        return match (true) {
            ! $deal->isActive() => ['غیرفعال', 'gray'],
            $deal->getEndsAt()->isPast() => ['تمام‌شده', 'gray'],
            $deal->isSoldOut() => ['فروش کامل', 'danger'],
            $deal->getStartsAt()->isFuture() => ['زمان‌بندی‌شده', 'warning'],
            default => ['در حال اجرا', 'success'],
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFlashDeals::route('/'),
            'create' => CreateFlashDeal::route('/create'),
            'edit' => EditFlashDeal::route('/{record}/edit'),
        ];
    }
}
