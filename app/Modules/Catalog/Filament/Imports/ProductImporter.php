<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Imports;

use App\Modules\Catalog\Actions\CreateProduct;
use App\Modules\Catalog\Actions\SaveProductVariant;
use App\Modules\Catalog\Actions\UpdateProduct;
use App\Modules\Catalog\Data\ProductData;
use App\Modules\Catalog\Data\ProductVariantData;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\Exceptions\AttributeChangeRefused;
use App\Modules\Catalog\Exceptions\ProductChangeRefused;
use App\Modules\Catalog\Jobs\DownloadImportedProductImages;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Support\CatalogImportValues;
use App\Modules\Shared\Support\PersianDigits;
use App\Modules\Shared\Support\PersianNormalizer;
use App\Modules\Shared\Support\Toman;
use Closure;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Validation\Rule;

/**
 * Catalog import from CSV (owner, 2026-11-27), Filament's import action on «محصولات»: the basic data only, one SKU per
 * row with its product's details and image URLs (separated by commas). No brand, category, supplier or supplier
 * price: staff set them in the admin, and until a product has its brand, category and subcategory it cannot be active
 * and never reaches the shop. Rows are processed in chunks of 100 on the «catalog-import» queue; a row that fails is
 * kept with its reason for the admin's failed-rows CSV.
 *
 * A row whose SKU code exists updates that SKU and its product; otherwise the one product with the row's name is used,
 * or a new draft product is created with this row as its first SKU. Filled cells overwrite, empty cells keep the
 * current value. Images are downloaded afterwards (DownloadImportedProductImages).
 *
 * @property ProductVariant $record
 */
final class ProductImporter extends Importer
{
    public const string QUEUE = 'catalog-import';

    public const int CHUNK_SIZE = 100;

    protected static ?string $model = ProductVariant::class;

    /**
     * Product text columns => products columns.
     */
    private const array TEXTS = [
        'name_en' => Product::COLUMN_NAME_EN,
        'irc_code' => Product::COLUMN_IRC_CODE,
        'short_description' => Product::COLUMN_SHORT_DESCRIPTION,
        'description' => Product::COLUMN_DESCRIPTION,
        'ingredients' => Product::COLUMN_INGREDIENTS,
        'usage' => Product::COLUMN_USAGE,
        'side_effects' => Product::COLUMN_SIDE_EFFECTS,
        'interactions' => Product::COLUMN_INTERACTIONS,
        'warnings' => Product::COLUMN_WARNINGS,
    ];

    /**
     * SKU columns => product_variants columns.
     */
    private const array VARIANT_FIELDS = [
        'sku' => ProductVariant::COLUMN_SKU,
        'title_fa' => ProductVariant::COLUMN_TITLE_FA,
        'barcode' => ProductVariant::COLUMN_BARCODE,
        'official_price' => ProductVariant::COLUMN_OFFICIAL_PRICE,
        'weight_g' => ProductVariant::COLUMN_WEIGHT_G,
        'length_mm' => ProductVariant::COLUMN_LENGTH_MM,
        'width_mm' => ProductVariant::COLUMN_WIDTH_MM,
        'height_mm' => ProductVariant::COLUMN_HEIGHT_MM,
        'max_per_order' => ProductVariant::COLUMN_MAX_PER_ORDER,
        'is_active' => ProductVariant::COLUMN_IS_ACTIVE,
    ];

    public static function getColumns(): array
    {
        $text = fn (string $name, string $label, int $max = 65535): ImportColumn => ImportColumn::make($name)->label($label)->guess([$label])->exampleHeader($label)->rules(['nullable', 'string', 'max:'.$max]);
        $whole = fn (string $name, string $label, int $min = 0): ImportColumn => ImportColumn::make($name)->label($label)->guess([$label])->exampleHeader($label)
            ->castStateUsing(fn (mixed $state): ?string => CatalogImportValues::number($state))
            ->rules(['nullable', 'integer', 'min:'.$min]);

        return [
            $text('name_fa', 'نام محصول', 255)->requiredMapping()->rules(['required', 'string', 'max:255'])->example('ویتامین D3 ۱۰۰۰'),
            $text('name_en', 'نام انگلیسی', 255)->example('Vitamin D3 1000'),
            ImportColumn::make('product_type')
                ->label('نوع محصول')
                ->guess(['نوع محصول'])->exampleHeader('نوع محصول')
                ->castStateUsing(fn (mixed $state): mixed => CatalogImportValues::productType($state) instanceof ProductType ? CatalogImportValues::productType($state)->value : (blank($state) ? null : $state))
                ->rules(['nullable', Rule::enum(ProductType::class)])
                ->helperText(implode('، ', array_map(fn (ProductType $type): string => $type->label(), ProductType::cases())))
                ->example(ProductType::Supplement->label()),
            $text('irc_code', 'کد IRC', 32),
            $text('short_description', 'توضیح کوتاه', 500),
            $text('description', 'توضیحات'),
            $text('ingredients', 'ترکیبات'),
            $text('usage', 'نحوه مصرف'),
            $text('side_effects', 'عوارض جانبی'),
            $text('interactions', 'تداخلات دارویی'),
            $text('warnings', 'هشدارها'),
            ImportColumn::make('images')
                ->label('تصاویر')
                ->guess(['تصاویر'])->exampleHeader('تصاویر')
                ->castStateUsing(fn (mixed $state): array => CatalogImportValues::imageUrls($state))
                ->rules(['array', 'max:'.CatalogImportValues::MAX_IMAGES, function (string $attribute, mixed $urls, Closure $fail): void {
                    foreach ((array) $urls as $url) {
                        if (filter_var($url, FILTER_VALIDATE_URL) === false || ! in_array(strtolower((string) parse_url((string) $url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
                            $fail('نشانی عکس باید با http یا https باشد: '.$url);
                        }
                    }
                }])
                ->helperText('نشانی‌های عمومی عکس‌های محصول، با ویرگول جدا شده؛ اولی عکس اصلی است.')
                ->example('https://example.com/a.jpg, https://example.com/b.jpg'),
            $text('sku', 'کد SKU', 64)->requiredMapping()->rules(['required', 'string', 'max:64'])->example('EV-D3-30'),
            $text('title_fa', 'نام SKU', 255)->example('۳۰ عددی'),
            $text('barcode', 'بارکد', 64)->example('6260123456789'),
            $whole('official_price', 'قیمت درج‌شده روی جعبه (تومان)')->example('485000'),
            $whole('weight_g', 'وزن (گرم)')->example('120'),
            $whole('length_mm', 'طول (میلی‌متر)'),
            $whole('width_mm', 'عرض (میلی‌متر)'),
            $whole('height_mm', 'ارتفاع (میلی‌متر)'),
            $whole('max_per_order', 'حداکثر تعداد در هر سفارش', 1),
            ImportColumn::make('is_active')
                ->label('فعال')
                ->guess(['فعال'])->exampleHeader('فعال')
                ->castStateUsing(fn (mixed $state): ?bool => CatalogImportValues::bool($state))
                ->rules(['nullable', 'boolean'])
                ->example('بله'),
        ];
    }

    /**
     * The SKU with the row's code, or a new one.
     */
    public function resolveRecord(): ProductVariant
    {
        return ProductVariant::query()->where(ProductVariant::COLUMN_SKU, trim((string) ($this->data['sku'] ?? '')))->first() ?? new ProductVariant;
    }

    /**
     * Values are saved through the catalog actions in saveRecord(), not copied onto the SKU.
     */
    public function fillRecord(): void {}

    /**
     * Saves the row: the product (created as a draft or updated), then the SKU; images follow in
     * DownloadImportedProductImages.
     *
     * @throws RowImportFailedException
     */
    public function saveRecord(): void
    {
        $variant = $this->record->exists ? $this->record : null;
        $product = $variant?->getProduct() ?? $this->productByName();

        try {
            if (! $product instanceof Product) {
                $this->assertFilled(['نوع محصول' => 'product_type', 'نام SKU' => 'title_fa'], 'محصول جدید است');
                $product = app(CreateProduct::class)(self::productData(null, $this->productValues()), $this->variantData(null));
                $this->record = $product->getDefaultVariant() ?? $this->record;
            } else {
                $product = app(UpdateProduct::class)($product, self::productData($product, $this->productValues()));

                if ($variant === null) {
                    $this->assertFilled(['نام SKU' => 'title_fa'], 'SKU جدید است');
                }

                $this->record = app(SaveProductVariant::class)($product, $this->variantData($variant), $variant);
            }
        } catch (ProductChangeRefused|AttributeChangeRefused $refused) {
            throw new RowImportFailedException($refused->getMessage());
        }

        $images = array_values((array) ($this->data['images'] ?? []));

        if ($images !== []) {
            DownloadImportedProductImages::dispatch($product->getId(), $images, $this->adminId())->afterCommit();
        }
    }

    /**
     * The product's data: the given product columns over the current product (a new product starts as a draft).
     *
     * @param  array<string, mixed>  $values  products columns to set
     */
    public static function productData(?Product $product, array $values): ProductData
    {
        $current = $product instanceof Product
            ? [
                ...$product->attributesToArray(),
                'attribute_value_ids' => $product->attributeValues()->pluck('attribute_values.id')->all(),
                'tag_ids' => $product->tags()->pluck('tags.id')->all(),
                'subcategory_ids' => $product->subcategories()->pluck('subcategories.id')->all(),
            ]
            : [Product::COLUMN_STATUS => ProductStatus::Draft];

        return ProductData::fromArray([...$current, ...$values]);
    }

    public function getJobQueue(): string
    {
        return self::QUEUE;
    }

    public static function getCompletedNotificationTitle(Import $import): string
    {
        return 'ورود کالا تمام شد';
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = PersianDigits::toPersian((string) $import->successful_rows).' ردیف از '.PersianDigits::toPersian((string) $import->total_rows).' ردیف وارد شد.';
        $failed = $import->getFailedRowsCount();

        if ($failed > 0) {
            $body .= ' '.PersianDigits::toPersian((string) $failed).' ردیف وارد نشد؛ فایل ردیف‌های ناموفق را با دلیل هر کدام دانلود کنید.';
        }

        return $body.' برند، دسته و زیردسته محصولات تازه را در پنل تعیین کنید؛ تا آن وقت در فروشگاه نمایش داده نمی‌شوند.';
    }

    /**
     * The row's filled product cells as products columns.
     *
     * @return array<string, mixed>
     */
    private function productValues(): array
    {
        $values = [Product::COLUMN_NAME_FA => (string) $this->data['name_fa']];

        if (filled($this->data['product_type'] ?? null)) {
            $values[Product::COLUMN_PRODUCT_TYPE] = (string) $this->data['product_type'];
        }

        foreach (self::TEXTS as $field => $column) {
            if (filled($this->data[$field] ?? null)) {
                $values[$column] = (string) $this->data[$field];
            }
        }

        return $values;
    }

    /**
     * The SKU's data: the row's filled cells over the current SKU (price in toman becomes rial).
     */
    private function variantData(?ProductVariant $variant): ProductVariantData
    {
        $values = $variant instanceof ProductVariant ? $variant->attributesToArray() : [ProductVariant::COLUMN_IS_ACTIVE => true];

        foreach (self::VARIANT_FIELDS as $field => $column) {
            $value = $this->data[$field] ?? null;

            if ($value !== null && $value !== '') {
                $values[$column] = $field === 'official_price' ? Toman::toRial((int) $value) : $value;
            }
        }

        return ProductVariantData::fromArray($values);
    }

    /**
     * The one product with the row's name; several products with that name fail the row.
     *
     * @throws RowImportFailedException
     */
    private function productByName(): ?Product
    {
        $name = trim((string) $this->data['name_fa']);
        $key = CatalogImportValues::nameKey($name);
        // Saved names have Persian letters (ProductData); spaces and Latin case are compared after loading.
        $products = Product::query()
            ->whereIn(Product::COLUMN_NAME_FA, array_values(array_unique([$name, PersianNormalizer::letters($name), (string) preg_replace('/\s+/u', ' ', PersianNormalizer::letters($name))])))
            ->get()
            ->filter(fn (Product $product): bool => CatalogImportValues::nameKey($product->getNameFa()) === $key);

        if ($products->count() > 1) {
            throw new RowImportFailedException('چند محصول با این نام هست؛ کد SKU یکی از SKUهای همان محصول را بنویسید.');
        }

        return $products->first();
    }

    /**
     * @param  array<string, string>  $fields  Persian column name => field
     *
     * @throws RowImportFailedException
     */
    private function assertFilled(array $fields, string $reason): void
    {
        $missing = array_keys(array_filter($fields, fn (string $field): bool => blank($this->data[$field] ?? null)));

        if ($missing !== []) {
            throw new RowImportFailedException($reason.'؛ این ستون‌ها را پر کنید: '.implode('، ', $missing).'.');
        }
    }

    private function adminId(): ?int
    {
        $adminId = $this->getImport()->getAttribute('user_id');

        return $adminId === null ? null : (int) $adminId;
    }
}
