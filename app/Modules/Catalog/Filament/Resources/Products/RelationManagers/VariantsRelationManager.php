<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Products\RelationManagers;

use App\Modules\Catalog\Actions\DeleteProductVariant;
use App\Modules\Catalog\Actions\ReorderProductVariants;
use App\Modules\Catalog\Actions\SaveProductVariant;
use App\Modules\Catalog\Actions\SetDefaultVariant;
use App\Modules\Catalog\Data\ProductVariantData;
use App\Modules\Catalog\Enums\StockStatus;
use App\Modules\Catalog\Exceptions\ProductChangeRefused;
use App\Modules\Catalog\Http\Requests\StoreProductVariantRequest;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Pricing\Actions\CalculateVariantPrice;
use App\Modules\Pricing\Filament\Pages\SkuSuppliers;
use App\Modules\Pricing\Filament\Support\PricingGuide;
use App\Modules\Pricing\Queries\ActiveSuppliers;
use App\Modules\Pricing\Queries\SupplierPrices;
use App\Modules\Shared\Support\Toman;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Variants of a product (roadmap D3.1): sizes in mm, weight in grams. The printed price and haraj are typed in toman
 * (saved as rial); the selling price is calculated by Pricing from the active supplier and the commission (D3.2) and
 * only shown here. Saving goes through SaveProductVariant; refusals are shown as notifications. Admins who can see
 * suppliers get each SKU's active supplier and a link to its suppliers page.
 */
final class VariantsRelationManager extends RelationManager
{
    use AuthorizesThroughProduct;

    protected static string $relationship = 'variants';

    protected static ?string $title = 'تنوع‌ها';

    protected static ?string $modelLabel = 'تنوع';

    protected static ?string $pluralModelLabel = 'تنوع‌ها';

    /** @var array<int, string>|null */
    private ?array $activeSupplierNames = null;

    /** @var array<int, bool>|null */
    private ?array $variantsWithSuppliers = null;

    public function form(Schema $schema): Schema
    {
        $rules = fn (?ProductVariant $record, string $field): array => StoreProductVariantRequest::baseRules($record?->getId())[$field];
        $millimetres = fn (string $field, string $label): TextInput => TextInput::make($field)
            ->label($label)
            ->numeric()
            ->suffix('میلی‌متر')
            ->rules(fn (?ProductVariant $record): array => $rules($record, $field));

        return $schema
            ->columns(1)
            ->components([
                Section::make('مشخصات')
                    ->columns(3)
                    ->schema([
                        TextInput::make(ProductVariant::COLUMN_TITLE_FA)
                            ->label('عنوان تنوع')
                            ->placeholder('مثلاً ۳۰ عددی یا ۵۰ میلی‌لیتر')
                            ->required()
                            ->rules(fn (?ProductVariant $record): array => $rules($record, ProductVariant::COLUMN_TITLE_FA)),
                        TextInput::make(ProductVariant::COLUMN_SKU)
                            ->label('SKU')
                            ->extraInputAttributes(['dir' => 'ltr'])
                            ->helperText('خالی بگذارید تا خودکار ساخته شود.')
                            ->rules(fn (?ProductVariant $record): array => $rules($record, ProductVariant::COLUMN_SKU)),
                        TextInput::make(ProductVariant::COLUMN_BARCODE)
                            ->label('بارکد')
                            ->extraInputAttributes(['dir' => 'ltr'])
                            ->rules(fn (?ProductVariant $record): array => $rules($record, ProductVariant::COLUMN_BARCODE)),
                    ]),
                Section::make('قیمت')
                    ->columns(3)
                    ->collapsible()
                    ->schema([
                        PricingGuide::skuForm()->columnSpanFull(),
                        TextInput::make(ProductVariant::COLUMN_OFFICIAL_PRICE)
                            ->label('قیمت مصرف‌کننده (روی جعبه)')
                            ->toman() // @phpstan-ignore method.notFound (macro, AdminPanelProvider)
                            ->live(onBlur: true)
                            ->helperText('سقف قیمت فروش و قیمت خط‌خورده در سایت. برای دارو الزامی است.'),
                        TextEntry::make('calculated_price')
                            ->label('قیمت فروش (محاسبه خودکار)')
                            ->state(fn (Get $get, ?ProductVariant $record): string => $this->expectedPrice($record, Toman::rialFromInput($get(ProductVariant::COLUMN_OFFICIAL_PRICE))))
                            ->helperText('قابل ویرایش نیست: قیمت خرید تأمین‌کننده فعال × (۱ + کمیسیون).'),
                        TextInput::make(ProductVariant::COLUMN_SALE_PRICE)
                            ->label('قیمت حراج')
                            ->toman() // @phpstan-ignore method.notFound (macro, AdminPanelProvider)
                            ->disabled(fn (?ProductVariant $record): bool => ($record?->getPrice() ?? 0) === 0)
                            ->helperText(fn (?ProductVariant $record): string => ($record?->getPrice() ?? 0) === 0
                                ? 'بعد از محاسبه قیمت فروش (افزودن تأمین‌کننده) قابل وارد کردن است.'
                                : 'خالی یعنی بدون حراج؛ باید کمتر از قیمت فروش باشد.'),
                    ]),
                Section::make('موجودی و تاریخ انقضا')
                    ->columns(3)
                    ->schema([
                        Select::make(ProductVariant::COLUMN_STOCK_STATUS)
                            ->label('وضعیت موجودی')
                            ->options(StockStatus::options())
                            ->default(StockStatus::OutOfStock->value)
                            ->disabled()
                            ->dehydrated()
                            ->helperText('خودکار: با تأمین‌کننده فعال یا قیمت روی جعبه «موجود»، بدون هیچ‌کدام «ناموجود».')
                            ->required(),
                        DatePicker::make(ProductVariant::COLUMN_EXPIRY_DATE)
                            ->label('تاریخ انقضا')
                            ->helperText('کمتر از ۹۰ روز مانده: خودکار «زودمصرف» می‌شود.'),
                        Toggle::make(ProductVariant::COLUMN_IS_DAMAGED_BOX)
                            ->label('کج‌جعبه')
                            ->inline(false),
                    ]),
                Section::make('ارسال')
                    ->description('ابعاد و وزن بسته‌بندی‌شده؛ برای محاسبه هزینه ارسال تیپاکس.')
                    ->columns(4)
                    ->schema([
                        TextInput::make(ProductVariant::COLUMN_WEIGHT_G)
                            ->label('وزن')
                            ->numeric()
                            ->suffix('گرم')
                            ->rules(fn (?ProductVariant $record): array => $rules($record, ProductVariant::COLUMN_WEIGHT_G)),
                        $millimetres(ProductVariant::COLUMN_LENGTH_MM, 'طول'),
                        $millimetres(ProductVariant::COLUMN_WIDTH_MM, 'عرض'),
                        $millimetres(ProductVariant::COLUMN_HEIGHT_MM, 'ارتفاع'),
                        Toggle::make(ProductVariant::COLUMN_DIMENSIONS_VERIFIED)->label('ابعاد اندازه‌گیری شده'),
                        Toggle::make(ProductVariant::COLUMN_SHIP_SEPARATELY)->label('ارسال جداگانه'),
                        Toggle::make(ProductVariant::COLUMN_IS_FRAGILE)->label('شکستنی'),
                        Toggle::make(ProductVariant::COLUMN_IS_LIQUID)->label('مایع'),
                        Toggle::make(ProductVariant::COLUMN_REQUIRES_COLD_CHAIN)
                            ->label('نیاز به زنجیره سرد')
                            ->helperText('ارسال آن در فاز اول ممکن نیست.'),
                    ]),
                Section::make('محدودیت‌ها')
                    ->columns(2)
                    ->schema([
                        TextInput::make(ProductVariant::COLUMN_MAX_PER_ORDER)
                            ->label('حداکثر تعداد در هر سفارش')
                            ->numeric()
                            ->rules(fn (?ProductVariant $record): array => $rules($record, ProductVariant::COLUMN_MAX_PER_ORDER)),
                        Toggle::make(ProductVariant::COLUMN_IS_ACTIVE)
                            ->label('فعال')
                            ->default(true)
                            ->inline(false),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute(ProductVariant::COLUMN_TITLE_FA)
            ->defaultSort(ProductVariant::COLUMN_SORT)
            ->reorderable(ProductVariant::COLUMN_SORT, fn (): bool => $this->canChange())
            ->description('ترتیب ردیف‌ها را با کشیدن تغییر دهید: اگر SKU پیش‌فرض ناموجود شود، اولین تنوع موجود به همین ترتیب جای آن را می‌گیرد.')
            ->columns([
                TextColumn::make(ProductVariant::COLUMN_TITLE_FA)
                    ->label('عنوان')
                    ->badge(fn (ProductVariant $record): bool => $this->isChosenDefault($record))
                    ->color(fn (ProductVariant $record): ?string => $this->isChosenDefault($record) ? 'primary' : null)
                    ->description(fn (ProductVariant $record): ?string => $this->defaultNote($record)),
                TextColumn::make(ProductVariant::COLUMN_SKU)->label('SKU')->searchable()->copyable(),
                TextColumn::make(ProductVariant::COLUMN_OFFICIAL_PRICE)->label('روی جعبه')->toman()->placeholder('—'), // @phpstan-ignore method.notFound (macro)
                TextColumn::make(ProductVariant::COLUMN_PRICE)->label('قیمت فروش')->toman(), // @phpstan-ignore method.notFound (macro)
                TextColumn::make(ProductVariant::COLUMN_SALE_PRICE)->label('قیمت حراج')->toman()->placeholder('—'), // @phpstan-ignore method.notFound (macro)
                TextColumn::make('active_supplier')
                    ->label('تأمین‌کننده فعال')
                    ->visible(fn (): bool => $this->canSeeSuppliers())
                    ->state(fn (ProductVariant $record): ?string => $this->activeSuppliers()[$record->getId()] ?? null)
                    ->placeholder(fn (ProductVariant $record): string => $this->hasSuppliers()[$record->getId()] ?? false ? 'ندارد: قیمت روی جعبه' : 'بدون تأمین‌کننده: قیمت روی جعبه'),
                TextColumn::make(ProductVariant::COLUMN_STOCK_STATUS)
                    ->label('موجودی')
                    ->badge()
                    ->formatStateUsing(fn (StockStatus $state): string => $state->label())
                    ->color(fn (StockStatus $state): string => $state->color()),
                TextColumn::make(ProductVariant::COLUMN_EXPIRY_DATE)
                    ->label('انقضا')
                    ->jalaliDate() // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
                    ->placeholder('—')
                    ->color(fn (ProductVariant $record): ?string => $record->isNearExpiry() ? 'warning' : null),
                IconColumn::make(ProductVariant::COLUMN_IS_ACTIVE)->label('فعال')->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->visible(fn (): bool => $this->canChange())
                    ->using(fn (array $data): ProductVariant => $this->save($data)),
            ])
            ->recordActions([
                Action::make('suppliers')
                    ->label('تأمین‌کنندگان')
                    ->icon(Heroicon::OutlinedTruck)
                    ->color('gray')
                    ->visible(fn (): bool => $this->canSeeSuppliers())
                    ->url(fn (ProductVariant $record): string => SkuSuppliers::getUrl(['variant' => $record->getId()])),
                Action::make('makeDefault')
                    ->label('پیش‌فرض کن')
                    ->icon('heroicon-o-star')
                    ->visible(fn (ProductVariant $record): bool => $this->canChange() && $record->isActive() && ! $this->isChosenDefault($record))
                    ->action(function (ProductVariant $record, SetDefaultVariant $setDefault): void {
                        try {
                            $setDefault($this->product(), $record);
                        } catch (ProductChangeRefused $refused) {
                            Notification::make()->danger()->title($refused->getMessage())->send();

                            return;
                        }

                        Notification::make()->success()->title('SKU پیش‌فرض تغییر کرد.')->send();
                    }),
                EditAction::make()
                    ->visible(fn (): bool => $this->canChange())
                    ->using(fn (ProductVariant $record, array $data): ProductVariant => $this->save($data, $record)),
                DeleteAction::make()
                    ->visible(fn (): bool => $this->canChange())
                    ->using(function (ProductVariant $record, Action $action, DeleteProductVariant $deleteVariant): bool {
                        try {
                            $deleteVariant($record);
                        } catch (ProductChangeRefused $refused) {
                            $action->failureNotificationTitle($refused->getMessage());

                            return false;
                        }

                        return true;
                    }),
            ]);
    }

    /**
     * Drag and drop goes through ReorderProductVariants (not Filament's direct update), so the search document
     * follows the new order.
     *
     * @param  array<int|string>  $order
     */
    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        abort_unless($this->canChange(), 403);

        app(ReorderProductVariants::class)($this->product(), array_values(array_map(intval(...), $order)));
    }

    /**
     * The selling price the SKU would have with this printed price: the active supplier's cost × (1 + commission),
     * rounded, capped by the printed price; or why there is none yet.
     */
    private function expectedPrice(?ProductVariant $variant, ?int $officialPrice): string
    {
        $chosen = $variant instanceof ProductVariant ? (app(ActiveSuppliers::class)->forVariants([$variant->getId()])[$variant->getId()] ?? null) : null;

        if ($chosen === null) {
            return $officialPrice !== null && $officialPrice > 0
                ? Toman::format($officialPrice).' (بدون تأمین‌کننده فعال: قیمت روی جعبه)'
                : 'بدون تأمین‌کننده فعال و قیمت روی جعبه، تنوع ناموجود است';
        }

        $price = CalculateVariantPrice::sellingPrice($chosen->offer->price->getCost(), (float) $chosen->offer->commissionPercent);
        $capped = $officialPrice !== null && $officialPrice > 0 && $price > $officialPrice;

        return Toman::format($capped ? $officialPrice : $price).($capped ? ' (سقف روی جعبه)' : '');
    }

    private function canSeeSuppliers(): bool
    {
        return auth('admin')->user()?->can('ViewAny:Provider') ?? false;
    }

    /**
     * @return array<int, string> variant id => active supplier name
     */
    private function activeSuppliers(): array
    {
        return $this->activeSupplierNames ??= app(ActiveSuppliers::class)->names($this->variantIds());
    }

    /**
     * @return array<int, bool>
     */
    private function hasSuppliers(): array
    {
        return $this->variantsWithSuppliers ??= app(SupplierPrices::class)->hasSuppliers($this->variantIds());
    }

    /**
     * @return list<int>
     */
    private function variantIds(): array
    {
        return array_values(array_map(intval(...), $this->product()->variants()->pluck(ProductVariant::COLUMN_ID)->all()));
    }

    /**
     * Which row is the default, and which one the site shows while the chosen default is out of stock.
     */
    private function defaultNote(ProductVariant $variant): ?string
    {
        $shown = $this->product()->getDefaultVariant()?->getId() === $variant->getId();

        return match (true) {
            $this->isChosenDefault($variant) && $shown => 'SKU پیش‌فرض',
            $this->isChosenDefault($variant) => 'SKU پیش‌فرض (ناموجود؛ فعلاً نمایش داده نمی‌شود)',
            $shown && $this->product()->getDefaultVariantId() !== null => 'در سایت به‌جای پیش‌فرض ناموجود',
            $shown => 'در سایت نمایش داده می‌شود',
            default => null,
        };
    }

    private function isChosenDefault(ProductVariant $variant): bool
    {
        return $this->product()->getDefaultVariantId() === $variant->getId();
    }

    private function product(): Product
    {
        /** @var Product $product */
        $product = $this->getOwnerRecord();

        return $product;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function save(array $data, ?ProductVariant $variant = null): ProductVariant
    {
        $product = $this->product();

        try {
            return app(SaveProductVariant::class)($product, ProductVariantData::fromArray($data), $variant);
        } catch (ProductChangeRefused $refused) {
            Notification::make()->danger()->title($refused->getMessage())->persistent()->send();
            throw new Halt($refused->getMessage(), $refused->getCode(), $refused);
        }
    }
}
