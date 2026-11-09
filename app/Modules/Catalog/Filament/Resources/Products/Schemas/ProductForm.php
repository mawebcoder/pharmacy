<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Products\Schemas;

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Enums\ProductType;
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
use App\Modules\Media\Filament\Forms\ImageUpload;
use App\Modules\Pricing\Filament\Support\PricingGuide;
use App\Modules\Shared\Support\PersianDigits;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

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
                                PricingGuide::productCommission()->columnSpanFull(),
                                TextInput::make(Product::COLUMN_SLUG)
                                    ->label('نامک (آدرس)')
                                    ->prefix('/products/')
                                    ->extraInputAttributes(['dir' => 'ltr'])
                                    ->maxLength(255)
                                    ->rules(fn (?Product $record): array => $rules($record, Product::COLUMN_SLUG))
                                    ->helperText(fn (?Product $record): string => $record instanceof Product
                                        ? 'تغییر نامک، پیوندهای قبلی این محصول را از کار می‌اندازد.'
                                        : 'خالی بگذارید تا از نام انگلیسی (یا فارسی) ساخته شود.'),
                                ImageSelect::images(Select::make(Product::COLUMN_PRIMARY_CATEGORY_ID), 'category', self::categoryOptions(...))
                                    ->label('دسته اصلی')
                                    ->required()
                                    ->rules(fn (?Product $record): array => $rules($record, Product::COLUMN_PRIMARY_CATEGORY_ID)),
                                ImageSelect::images(Select::make('category_ids'), 'category', self::categoryOptions(...))
                                    ->label('دسته‌های دیگر')
                                    ->multiple()
                                    ->rules(fn (?Product $record): array => $rules($record, 'category_ids'))
                                    ->nestedRecursiveRules(StoreProductRequest::baseRules()['category_ids.*']),
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
                                Toggle::make(Product::COLUMN_REQUIRES_PRESCRIPTION)
                                    ->label('نیاز به نسخه')
                                    ->helperText('محصول نیازمند نسخه قابل فعال‌سازی برای فروش نیست.'),
                                Select::make(Product::COLUMN_STATUS)
                                    ->label('وضعیت')
                                    ->options(ProductStatus::options())
                                    ->default(ProductStatus::Draft->value)
                                    ->required()
                                    ->hiddenOn('create')
                                    ->helperText('برای «فعال»: برند، دسته اصلی، دست‌کم یک تنوع فعال با قیمت و یک تصویر لازم است.'),
                                self::firstVariantSection(),
                            ]),
                        Tab::make('ویژگی‌ها و برچسب‌ها')
                            ->columns(2)
                            ->schema([...self::attributeFields(), self::tagField()]),
                        Tab::make('محتوا')
                            ->schema([
                                $text(Product::COLUMN_SHORT_DESCRIPTION, 'توضیح کوتاه', 2),
                                $text(Product::COLUMN_DESCRIPTION, 'توضیحات', 6),
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
     * The first SKU, asked for on create so every product has one (S13b). More SKUs, sizes, shipping details and the
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
     * Every category as a path ("مکمل‌ها › ویتامین‌ها › ویتامین D").
     *
     * @return array<int, string>
     */
    private static function categoryOptions(): array
    {
        $categories = Category::query()->get([Category::COLUMN_ID, Category::COLUMN_PARENT_ID, Category::COLUMN_NAME_FA])->keyBy(Category::COLUMN_ID);
        $options = [];

        foreach ($categories as $category) {
            $path = [$category->getNameFa()];
            $parentId = $category->getParentId();

            while ($parentId !== null && $categories->has($parentId) && count($path) < Category::MAX_DEPTH) {
                /** @var Category $parent */
                $parent = $categories->get($parentId);
                array_unshift($path, $parent->getNameFa());
                $parentId = $parent->getParentId();
            }

            $options[$category->getId()] = implode(' › ', $path);
        }

        asort($options);

        return $options;
    }
}
