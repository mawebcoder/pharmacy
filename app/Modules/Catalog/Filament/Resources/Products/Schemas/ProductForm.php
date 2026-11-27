<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Products\Schemas;

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\Filament\Forms\BoxCapacityFields;
use App\Modules\Catalog\Filament\Forms\DescriptionEditor;
use App\Modules\Catalog\Filament\Forms\ImageSelect;
use App\Modules\Catalog\Http\Requests\StoreProductRequest;
use App\Modules\Catalog\Http\Requests\StoreProductVariantRequest;
use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Models\AttributeValue;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\Tag;
use App\Modules\Catalog\Queries\AdminOptions;
use App\Modules\Catalog\Support\ProductVisibility;
use App\Modules\Media\Filament\Forms\ImageUpload;
use App\Modules\Pricing\Filament\Support\PricingGuide;
use App\Modules\Pricing\Livewire\ProductProvidersTable;
use App\Modules\Settings\Queries\StoreSettings;
use App\Modules\Shared\Support\PersianDigits;
use App\Modules\Shipping\Enums\TipaxReferenceType;
use App\Modules\Shipping\Queries\TipaxReferences;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

/**
 * Tabs in roadmap B12 order (General → Attributes & tags → Content → Images → SEO). Field rules come from StoreProductRequest::baseRules()
 * so admin and HTTP validation never drift (roadmap C5).
 */
final class ProductForm
{
    private const string ATTRIBUTE_STATE = 'attribute_values';

    /**
     * Form state key of the first SKU's fields on create.
     */
    public const string FIRST_VARIANT = 'first_variant';

    public static function configure(Schema $schema): Schema
    {
        $rules = fn (?Product $record, string $field): array => StoreProductRequest::baseRules($record?->getId())[$field];
        $text = fn (string $field, string $label, int $rows = 4): Textarea => Textarea::make($field)
            ->label($label)
            ->rows($rows)
            ->rules(fn (?Product $record): array => $rules($record, $field));

        return $schema
            ->columns(1)
            ->components([
                self::visibilityNote(),
                Tabs::make('product')
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('عمومی')
                            ->columns(2)
                            ->schema([
                                TextInput::make(Product::COLUMN_NAME_FA)
                                    ->label('نام فارسی')
                                    ->required()
                                    ->maxLength(255)
                                    ->rules(fn (?Product $record): array => $rules($record, Product::COLUMN_NAME_FA)),
                                TextInput::make(Product::COLUMN_NAME_EN)
                                    ->label('نام انگلیسی')
                                    ->extraInputAttributes(['dir' => 'ltr'])
                                    ->maxLength(255)
                                    ->rules(fn (?Product $record): array => $rules($record, Product::COLUMN_NAME_EN)),
                                ImageSelect::images(Select::make(Product::COLUMN_BRAND_ID), 'brand', fn (): array => Brand::query()->orderBy(Brand::COLUMN_NAME_FA)->pluck(Brand::COLUMN_NAME_FA, Brand::COLUMN_ID)->all())
                                    ->label('برند')
                                    ->required()
                                    ->rules(fn (?Product $record): array => $rules($record, Product::COLUMN_BRAND_ID)),
                                TextInput::make(Product::COLUMN_COMMISSION_PERCENT)
                                    ->label('کمیسیون')
                                    ->numeric()
                                    ->suffix('٪')
                                    ->placeholder(fn (Get $get): string => self::brandCommissionHint($get(Product::COLUMN_BRAND_ID)))
                                    ->rules(fn (?Product $record): array => $rules($record, Product::COLUMN_COMMISSION_PERCENT)),
                                TextInput::make(Product::COLUMN_HARAJ_PERCENT)
                                    ->label('تخفیف حراج')
                                    ->numeric()
                                    ->suffix('٪')
                                    ->rules(fn (?Product $record): array => $rules($record, Product::COLUMN_HARAJ_PERCENT))
                                    ->helperText('برای همه تنوع‌ها، از قیمت فروش؛ با تغییر قیمت خرید یا کمیسیون خودکار دوباره حساب می‌شود. هر تنوع می‌تواند درصد خودش را داشته باشد.'),
                                PricingGuide::productCommission()->columnSpanFull(),
                                Livewire::make(ProductProvidersTable::class, fn (?Product $record): array => ['product' => $record?->getId()])
                                    ->key('product-providers')
                                    ->visible(fn (?Product $record): bool => $record instanceof Product && (auth('admin')->user()?->can('ViewAny:Provider') ?? false))
                                    ->columnSpanFull(),
                                TextInput::make(Product::COLUMN_SLUG)
                                    ->label('نامک (آدرس)')
                                    ->prefix('/products/')
                                    ->extraInputAttributes(['dir' => 'ltr'])
                                    ->maxLength(255)
                                    ->rules(fn (?Product $record): array => $rules($record, Product::COLUMN_SLUG))
                                    ->helperText(fn (?Product $record): string => $record instanceof Product
                                        ? 'تغییر نامک، پیوندهای قبلی این محصول را از کار می‌اندازد.'
                                        : 'خالی بگذارید تا از نام انگلیسی (یا فارسی) ساخته شود.'),
                                // Products belong to subcategories (owner, 2026-11-15); the category follows the main one.
                                ImageSelect::images(Select::make(Product::COLUMN_PRIMARY_SUBCATEGORY_ID), 'subcategory', fn (): array => app(AdminOptions::class)->names('subcategory'))
                                    ->label('زیردسته اصلی')
                                    ->required()
                                    ->helperText('دسته محصول همان دستهٔ این زیردسته است.')
                                    ->rules(fn (?Product $record): array => $rules($record, Product::COLUMN_PRIMARY_SUBCATEGORY_ID)),
                                ImageSelect::images(Select::make('subcategory_ids'), 'subcategory', fn (): array => app(AdminOptions::class)->names('subcategory'))
                                    ->label('زیردسته‌های دیگر')
                                    ->multiple()
                                    ->rules(fn (?Product $record): array => $rules($record, 'subcategory_ids'))
                                    ->nestedRecursiveRules(StoreProductRequest::baseRules()['subcategory_ids.*']),
                                Select::make(Product::COLUMN_PRODUCT_TYPE)
                                    ->label('نوع محصول')
                                    ->options(ProductType::options())
                                    ->default(ProductType::Cosmetic->value)
                                    ->required()
                                    ->live()
                                    ->helperText('داروی نسخه‌ای قابل فعال‌سازی برای فروش نیست. دارو باید قیمت مصرف‌کننده (روی جعبه) داشته باشد.'),
                                TextInput::make(Product::COLUMN_IRC_CODE)
                                    ->label('کد IRC')
                                    ->extraInputAttributes(['dir' => 'ltr'])
                                    ->maxLength(32)
                                    ->rules(fn (?Product $record): array => $rules($record, Product::COLUMN_IRC_CODE)),
                                TextInput::make(Product::COLUMN_MAX_PER_ORDER)
                                    ->label('حداکثر تعداد در هر سفارش')
                                    ->numeric()
                                    ->integer()
                                    ->rules(fn (?Product $record): array => $rules($record, Product::COLUMN_MAX_PER_ORDER))
                                    ->helperText('برای هر تنوع این محصول؛ اگر تنوع عدد خودش را داشته باشد همان اعمال می‌شود. خالی یعنی تنظیم کلی فروشگاه.'),
                                TextInput::make(Product::COLUMN_MIN_SHELF_LIFE_MONTHS)
                                    ->label('تضمین اعتبار (ماه)')
                                    ->numeric()
                                    ->integer()
                                    ->suffix('ماه')
                                    ->rules(fn (?Product $record): array => $rules($record, Product::COLUMN_MIN_SHELF_LIFE_MONTHS))
                                    ->helperText('در صفحه محصول: «تضمین اعتبار: حداقل N ماه». تاریخ انقضای دقیق تضمین نمی‌شود؛ خالی یعنی نمایش داده نمی‌شود.'),
                                Select::make(Product::COLUMN_STATUS)
                                    ->label('وضعیت')
                                    ->options(ProductStatus::options())
                                    ->default(ProductStatus::Draft->value)
                                    ->required()
                                    ->hiddenOn('create')
                                    ->helperText('برای «فعال»: برند، دسته اصلی، دست‌کم یک تنوع فعال با قیمت و یک تصویر لازم است.'),
                                self::firstVariantSection(),
                            ]),
                        // What the product is for Tipax (owner, 2026-11-19): per product, for every one of its SKUs.
                        Tab::make('ارسال')
                            ->columns(2)
                            ->schema([
                                Select::make(Product::COLUMN_TIPAX_CONTENT_ID)
                                    ->label('محتوای بسته در تیپاکس')
                                    ->options(fn (): array => self::tipaxContentOptions())
                                    ->placeholder('پیش‌فرض فروشگاه')
                                    ->helperText('برای استعلام قیمت به تیپاکس فرستاده می‌شود. بسته‌ای که همه کالاهایش یک محتوا دارند همان را می‌گیرد؛ وگرنه محتوای پیش‌فرض فروشگاه.')
                                    ->rules(fn (?Product $record): array => $rules($record, Product::COLUMN_TIPAX_CONTENT_ID))
                                    ->columnSpanFull(),
                                Toggle::make(Product::COLUMN_IS_LIQUID)
                                    ->label('مایع')
                                    ->helperText('بسته‌ای که کالای مایع یا شکستنی دارد، «مایعات-شکستنی» فرستاده می‌شود.'),
                                Toggle::make(Product::COLUMN_IS_FRAGILE)->label('شکستنی'),
                            ]),
                        Tab::make('ویژگی‌ها و برچسب‌ها')
                            ->columns(2)
                            ->schema([...self::attributeFields(), self::tagField()]),
                        Tab::make('محتوا')
                            ->schema([
                                $text(Product::COLUMN_SHORT_DESCRIPTION, 'توضیح کوتاه', 2),
                                DescriptionEditor::make(Product::COLUMN_DESCRIPTION, maxLength: 20_000),
                                $text(Product::COLUMN_INGREDIENTS, 'ترکیبات'),
                                $text(Product::COLUMN_USAGE, 'روش مصرف'),
                                $text(Product::COLUMN_WARNINGS, 'هشدارها'),
                                $text(Product::COLUMN_SIDE_EFFECTS, 'عوارض جانبی'),
                                $text(Product::COLUMN_INTERACTIONS, 'تداخلات'),
                            ]),
                        Tab::make('تصاویر')
                            ->schema([
                                ImageUpload::make(Product::IMAGE_GALLERY, 'product')
                                    ->label('تصاویر محصول')
                                    ->multiple()
                                    ->reorderable()
                                    ->appendFiles()
                                    ->maxFiles(12)
                                    ->panelLayout('grid'),
                            ]),
                        Tab::make('سئو')
                            ->schema([
                                TextInput::make(Product::COLUMN_SEO_TITLE)
                                    ->label('عنوان صفحه')
                                    ->maxLength(255)
                                    ->rules(fn (?Product $record): array => $rules($record, Product::COLUMN_SEO_TITLE)),
                                Textarea::make(Product::COLUMN_SEO_DESCRIPTION)
                                    ->label('توضیحات متا')
                                    ->rows(3)
                                    ->maxLength(500)
                                    ->rules(fn (?Product $record): array => $rules($record, Product::COLUMN_SEO_DESCRIPTION)),
                            ]),
                    ]),
            ]);
    }

    /**
     * Tipax's package contents of the store's pack type (synced by `tipax:sync-reference`; S33), id => title.
     *
     * @return array<int, string>
     */
    private static function tipaxContentOptions(): array
    {
        $packType = app(StoreSettings::class)->tipaxCode(StoreSettings::TIPAX_PACK_TYPE) ?? (int) config('shipping.tipax.pricing.pack_type');

        return app(TipaxReferences::class)->options(TipaxReferenceType::Content, $packType);
    }

    /**
     * One select per attribute, in attribute order: single or multiple choice. State key: attribute_values.{code}.
     *
     * @return array<int, Select>
     */
    private static function attributeFields(): array
    {
        return Attribute::query()
            ->with('values')
            ->orderBy(Attribute::COLUMN_SORT)
            ->get()
            ->map(fn (Attribute $attribute): Select => Select::make(self::ATTRIBUTE_STATE.'.'.$attribute->getCode())
                ->label($attribute->getNameFa())
                ->options($attribute->getValues()->mapWithKeys(fn (AttributeValue $value): array => [$value->getId() => $value->getValueFa()])->all())
                ->multiple($attribute->isMultiple())
                ->searchable())
            ->values()
            ->all();
    }

    private static function tagField(): Select
    {
        return ImageSelect::images(Select::make('tag_ids'), 'tag', fn (): array => Tag::query()
            ->orderBy(Tag::COLUMN_NAME_FA)
            ->get()
            ->mapWithKeys(fn (Tag $tag): array => [$tag->getId() => $tag->getNameFa().' ('.$tag->getType()->label().')'])
            ->all())
            ->label('برچسب‌ها')
            ->multiple()
            ->nestedRecursiveRules(StoreProductRequest::baseRules()['tag_ids.*'])
            ->columnSpanFull();
    }

    /**
     * The first SKU, asked for on create so every product has one (S14). More SKUs, sizes, shipping details and the
     * suppliers (which give it a selling price, roadmap D3.2) are added on the edit page («تنوع‌ها»). Saved under
     * FIRST_VARIANT; the printed price is typed in toman. The SKU starts out of stock until it has a supplier.
     */
    private static function firstVariantSection(): Section
    {
        $rules = StoreProductVariantRequest::baseRules();

        return Section::make('اولین تنوع (SKU)')
            ->description('هر محصول دست‌کم یک تنوع دارد. قیمت فروش وارد نمی‌شود: بعد از ذخیره، در «تنوع‌ها» برای این تنوع تأمین‌کننده اضافه کنید تا قیمت فروش از قیمت خرید و کمیسیون ساخته شود. تا آن موقع تنوع «ناموجود» است.')
            ->statePath(self::FIRST_VARIANT)
            ->visibleOn('create')
            ->columnSpanFull()
            ->columns(3)
            ->schema([
                TextInput::make(ProductVariant::COLUMN_TITLE_FA)
                    ->label('عنوان تنوع')
                    ->placeholder('مثلاً ۳۰ عددی یا ۵۰ میلی‌لیتر')
                    ->required()
                    ->rules($rules[ProductVariant::COLUMN_TITLE_FA]),
                TextInput::make(ProductVariant::COLUMN_SKU)
                    ->label('SKU')
                    ->extraInputAttributes(['dir' => 'ltr'])
                    ->helperText('خالی بگذارید تا خودکار ساخته شود.')
                    ->rules($rules[ProductVariant::COLUMN_SKU]),
                TextInput::make(ProductVariant::COLUMN_OFFICIAL_PRICE)
                    ->label('قیمت مصرف‌کننده (روی جعبه)')
                    ->toman() // @phpstan-ignore method.notFound (macro, AdminPanelProvider)
                    ->helperText('سقف قیمت فروش. برای دارو الزامی است.'),
                TextInput::make(ProductVariant::COLUMN_WEIGHT_G)
                    ->label('وزن')
                    ->numeric()
                    ->suffix('گرم')
                    ->required()
                    ->helperText('وزن کالا با بسته‌بندی خودش؛ هزینه ارسال از آن حساب می‌شود.')
                    ->rules($rules[ProductVariant::COLUMN_WEIGHT_G]),
                BoxCapacityFields::section()->columnSpanFull(),
            ]);
    }

    /**
     * Placeholder of the product commission: what applies when it is left empty.
     */
    private static function brandCommissionHint(mixed $brandId): string
    {
        $commission = blank($brandId) ? null : Brand::query()->find((int) $brandId)?->getCommissionPercent();

        return $commission === null ? 'برند کمیسیون ندارد؛ وارد کنید' : 'خالی = کمیسیون برند ('.PersianDigits::toPersian(rtrim(rtrim(number_format($commission, 2), '0'), '.')).'٪)';
    }

    /**
     * The form state of a product's attribute values: attribute code => value id (single) or ids (multiple).
     *
     * @return array<string, int|list<int>>
     */
    public static function attributeState(Product $product): array
    {
        $multiple = [];
        $single = [];

        foreach ($product->attributeValues()->with('attribute')->get() as $value) {
            $attribute = $value->getAttributeModel();

            if ($attribute === null) {
                continue;
            }

            if ($attribute->isMultiple()) {
                $multiple[$attribute->getCode()][] = $value->getId();
            } else {
                $single[$attribute->getCode()] = $value->getId();
            }
        }

        return [...$single, ...$multiple];
    }

    /**
     * Turns the form state back into the flat attribute_value_ids list the actions take.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function withAttributeValueIds(array $data): array
    {
        /** @var array<string, int|string|list<int|string>|null> $state */
        $state = $data[self::ATTRIBUTE_STATE] ?? [];
        $ids = [];

        foreach ($state as $value) {
            foreach ((array) $value as $id) {
                if (filled($id)) {
                    $ids[] = (int) $id;
                }
            }
        }

        unset($data[self::ATTRIBUTE_STATE]);
        $data['attribute_value_ids'] = $ids;

        return $data;
    }

    /**
     * Whether the product is on the storefront (owner, 2026-11-27): the rules while creating; on edit, «shown» or
     * what keeps this one off the lists and search, from ProductVisibility.
     */
    private static function visibilityNote(): Callout
    {
        $problems = fn (?Product $record): array => $record instanceof Product ? ProductVisibility::problems($record) : [];

        return Callout::make(fn (?Product $record): string => match (true) {
            ! $record instanceof Product => 'نمایش در سایت',
            $problems($record) === [] => 'این محصول در سایت نمایش داده می‌شود',
            default => 'این محصول در فهرست‌ها و جستجوی سایت نمایش داده نمی‌شود',
        })
            ->key('storefront_visibility')
            ->status(fn (?Product $record): string => match (true) {
                ! $record instanceof Product => 'info',
                $problems($record) === [] => 'success',
                default => 'warning',
            })
            ->description(function (?Product $record) use ($problems): HtmlString {
                $reasons = $problems($record);
                $list = $reasons === [] ? '' : '<ul class="mt-1 list-inside list-disc">'.implode('', array_map(fn (string $reason): string => '<li>'.e($reason).'</li>', $reasons)).'</ul>';

                return new HtmlString($list.'<p class="mt-1">'.e(ProductVisibility::RULES).'</p>');
            });
    }
}
