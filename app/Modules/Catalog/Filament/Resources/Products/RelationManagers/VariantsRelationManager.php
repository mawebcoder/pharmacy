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
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

/**
 * Variants of a product (roadmap D3.1): prices typed in toman (saved as rial), sizes in mm, weight in grams.
 * Saving goes through SaveProductVariant (price rules, SKU, near-expiry); refusals are shown as notifications.
 */
final class VariantsRelationManager extends RelationManager
{
    use AuthorizesThroughProduct;

    protected static string $relationship = 'variants';

    protected static ?string $title = 'تنوع‌ها';

    protected static ?string $modelLabel = 'تنوع';

    protected static ?string $pluralModelLabel = 'تنوع‌ها';

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
                        $this->pricingGuide()->columnSpanFull(),
                        TextInput::make(ProductVariant::COLUMN_OFFICIAL_PRICE)
                            ->label('قیمت مصرف‌کننده (روی جعبه)')
                            ->toman() // @phpstan-ignore method.notFound (macro, AdminPanelProvider)
                            ->helperText('سقف قیمت فروش و قیمت خط‌خورده در سایت. برای دارو الزامی است.'),
                        TextInput::make(ProductVariant::COLUMN_PRICE)
                            ->label('قیمت فروش')
                            ->toman() // @phpstan-ignore method.notFound (macro, AdminPanelProvider)
                            ->required()
                            ->helperText('نباید از قیمت روی جعبه بیشتر باشد.'),
                        TextInput::make(ProductVariant::COLUMN_SALE_PRICE)
                            ->label('قیمت حراج')
                            ->toman() // @phpstan-ignore method.notFound (macro, AdminPanelProvider)
                            ->helperText('خالی یعنی بدون حراج؛ باید کمتر از قیمت فروش باشد.'),
                    ]),
                Section::make('موجودی و تاریخ انقضا')
                    ->columns(3)
                    ->schema([
                        Select::make(ProductVariant::COLUMN_STOCK_STATUS)
                            ->label('وضعیت موجودی')
                            ->options(StockStatus::options())
                            ->default(StockStatus::Available->value)
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
     * How the prices of a variant work, shown above the price fields. The automatic price (commission, provider
     * price) is added with the pricing engine (S15).
     */
    private function pricingGuide(): Callout
    {
        return Callout::make('قیمت‌ها چطور کار می‌کنند')
            ->info()
            ->description(new HtmlString(<<<'HTML'
                <p><strong>قیمت مصرف‌کننده (روی جعبه):</strong> قیمتی که روی بسته چاپ شده است. سقف قیمت است: قیمت فروش و قیمت حراج هیچ‌وقت از آن بیشتر نمی‌شوند. در سایت خط‌خورده نمایش داده می‌شود و درصد تخفیف نسبت به همین قیمت حساب می‌شود. برای دارو الزامی است.</p>
                <p><strong>قیمت فروش:</strong> قیمتی که مشتری وقتی حراج فعال نیست می‌پردازد. فعلاً دستی وارد می‌شود؛ با راه‌اندازی موتور قیمت‌گذاری از کمترین قیمت خرید تأمین‌کننده‌ها و کمیسیون محصول حساب خواهد شد.</p>
                <p><strong>قیمت حراج:</strong> قیمت تخفیف‌خورده؛ اختیاری است و باید کمتر از قیمت فروش باشد. خالی یعنی بدون حراج.</p>
                <p><strong>مشتری چه می‌پردازد؟</strong> قیمت حراج اگر ثبت شده باشد، در غیر این صورت قیمت فروش.</p>
                <p><strong>مثال:</strong> روی جعبه ۱۲۵٬۰۰۰، فروش ۱۲۰٬۰۰۰ و حراج ۱۱۰٬۰۰۰ تومان ← مشتری ۱۱۰٬۰۰۰ تومان می‌پردازد و در سایت <del>۱۲۵٬۰۰۰</del> با ۱۲٪ تخفیف دیده می‌شود. بدون حراج: <del>۱۲۵٬۰۰۰</del> ۱۲۰٬۰۰۰ تومان با ۴٪ تخفیف.</p>
                <p><strong>SKU پیش‌فرض:</strong> تنوعی که در کارت محصول، جستجو و دکمهٔ «افزودن به سبد» استفاده می‌شود (در جدول تنوع‌ها «پیش‌فرض کن»). اگر ناموجود شود، اولین تنوع موجود به ترتیب جدول جای آن را می‌گیرد و پس از موجود شدن دوباره، خودکار برمی‌گردد.</p>
                HTML))
            ->extraAttributes(['class' => '[&_p]:mt-2 [&_p:first-child]:mt-0']);
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
