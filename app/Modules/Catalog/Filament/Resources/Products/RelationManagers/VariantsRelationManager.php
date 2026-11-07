<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Products\RelationManagers;

use App\Modules\Catalog\Actions\DeleteProductVariant;
use App\Modules\Catalog\Actions\SaveProductVariant;
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
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
                    ->schema([
                        TextInput::make(ProductVariant::COLUMN_OFFICIAL_PRICE)
                            ->label('قیمت مصوب (روی جعبه)')
                            ->toman() // @phpstan-ignore method.notFound (macro, AdminPanelProvider)
                            ->helperText('برای دارو الزامی است و سقف قیمت فروش است.'),
                        TextInput::make(ProductVariant::COLUMN_PRICE)
                            ->label('قیمت فروش')
                            ->toman() // @phpstan-ignore method.notFound (macro, AdminPanelProvider)
                            ->required(),
                        TextInput::make(ProductVariant::COLUMN_SALE_PRICE)
                            ->label('قیمت حراج')
                            ->toman() // @phpstan-ignore method.notFound (macro, AdminPanelProvider)
                            ->helperText('خالی یعنی بدون تخفیف؛ باید کمتر از قیمت فروش باشد.'),
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
            ->columns([
                TextColumn::make(ProductVariant::COLUMN_TITLE_FA)->label('عنوان'),
                TextColumn::make(ProductVariant::COLUMN_SKU)->label('SKU')->searchable()->copyable(),
                TextColumn::make(ProductVariant::COLUMN_PRICE)->label('قیمت فروش')->toman(), // @phpstan-ignore method.notFound (macro)
                TextColumn::make(ProductVariant::COLUMN_SALE_PRICE)->label('حراج')->toman()->placeholder('—'), // @phpstan-ignore method.notFound (macro)
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
     * @param  array<string, mixed>  $data
     */
    private function save(array $data, ?ProductVariant $variant = null): ProductVariant
    {
        /** @var Product $product */
        $product = $this->getOwnerRecord();

        try {
            return app(SaveProductVariant::class)($product, ProductVariantData::fromArray($data), $variant);
        } catch (ProductChangeRefused $refused) {
            Notification::make()->danger()->title($refused->getMessage())->persistent()->send();
            throw new Halt($refused->getMessage(), $refused->getCode(), $refused);
        }
    }
}
