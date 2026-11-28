<?php

declare(strict_types=1);

use App\Modules\Catalog\Actions\ImportProductImages;
use App\Modules\Catalog\Actions\UpdateProduct;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\Exceptions\ProductChangeRefused;
use App\Modules\Catalog\Filament\Imports\ProductImporter;
use App\Modules\Catalog\Filament\Resources\Products\Pages\ListProducts;
use App\Modules\Catalog\Jobs\DownloadImportedProductImages;
use App\Modules\Catalog\Listeners\NotifyAdminOfImportChunk;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use App\Modules\Shipping\Database\Seeders\TipaxCartonsSeeder;
use Filament\Actions\Imports\Events\ImportChunkProcessed;
use Filament\Actions\Imports\Models\Import;
use Filament\Facades\Filament;
use Filament\Notifications\DatabaseNotification as FilamentDatabaseNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

/*
| Catalog import from CSV (owner, 2026-11-27): Filament's import action on «محصولات», one SKU per row with its
| product's basic data and image URLs; no brand or category (set later in the admin, required there), so imported
| products stay drafts and never reach the shop until they have them.
*/

beforeEach(function (): void {
    $this->seed(TipaxCartonsSeeder::class);
    $this->seed(RolesSeeder::class);
    Filament::setCurrentPanel('admin');
    Storage::fake(originalsDisk());
    Storage::fake(variantsDisk());
    $this->admin = AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::ContentEditor)->create();
});

/**
 * A filled row, as Filament hands it to the importer after the column mapping.
 *
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function catalogRow(array $overrides = []): array
{
    return [
        'name_fa' => 'ویتامین D3 ۱۰۰۰',
        'name_en' => 'Vitamin D3 1000',
        'product_type' => 'مکمل',
        'irc_code' => '',
        'short_description' => 'برای استخوان‌ها',
        'description' => 'توضیح کامل',
        'ingredients' => 'کوله‌کلسیفرول',
        'usage' => 'روزی یک عدد',
        'side_effects' => 'نادر',
        'interactions' => 'ندارد',
        'warnings' => 'دور از دسترس کودکان',
        'images' => '',
        'sku' => 'EV-D3-30',
        'title_fa' => '۳۰ عددی',
        'barcode' => '6260123456789',
        'official_price' => '۴۸۵٬۰۰۰',
        'weight_g' => '120',
        'length_mm' => '60',
        'width_mm' => '60',
        'height_mm' => '110',
        'max_per_order' => '',
        'is_active' => 'بله',
        ...$overrides,
    ];
}

function catalogImport(AdminUser $admin): Import
{
    return (new Import)->forceFill(['user_type' => $admin->getMorphClass(), 'user_id' => $admin->getKey()]);
}

it('creates a draft product with its basic data and first SKU, without brand or category', function (): void {
    ProductImporter::test(import: catalogImport($this->admin))->import(catalogRow())->assertImported();

    $product = Product::query()->sole();
    $variant = $product->getDefaultVariant();

    expect($product->getNameFa())->toBe('ویتامین D3 ۱۰۰۰')
        ->and($product->getStatus())->toBe(ProductStatus::Draft)
        ->and($product->getProductType())->toBe(ProductType::Supplement)
        ->and($product->getBrandId())->toBeNull()
        ->and($product->getPrimaryCategoryId())->toBeNull()
        ->and($product->getPrimarySubcategoryId())->toBeNull()
        ->and($product->getSideEffects())->toBe('نادر')
        ->and($product->getInteractions())->toBe('ندارد')
        ->and($variant?->getSku())->toBe('EV-D3-30')
        ->and($variant?->getTitleFa())->toBe('۳۰ عددی')
        ->and($variant?->getOfficialPrice())->toBe(4_850_000)
        ->and($variant?->getWeightG())->toBe(120);
});

it('adds a row with the same product name as another SKU of that product', function (): void {
    $import = ProductImporter::test(import: catalogImport($this->admin));

    $import->import(catalogRow())->assertImported();
    $import->import(catalogRow(['sku' => 'EV-D3-60', 'title_fa' => '۶۰ عددی', 'product_type' => '', 'description' => '']))->assertImported();

    $product = Product::query()->sole();

    expect($product->variants()->pluck(ProductVariant::COLUMN_SKU)->sort()->values()->all())->toBe(['EV-D3-30', 'EV-D3-60'])
        ->and($product->getDescription())->toBe('توضیح کامل');
});

it('updates an existing SKU and its product with the filled cells and keeps the rest, brand and category included', function (): void {
    $product = Product::factory()->create([Product::COLUMN_NAME_FA => 'نام قدیمی', Product::COLUMN_USAGE => 'روش قبلی']);
    $variant = ProductVariant::factory()->for($product)->create([ProductVariant::COLUMN_SKU => 'OLD-1', ProductVariant::COLUMN_BARCODE => '111']);

    ProductImporter::test(import: catalogImport($this->admin))
        ->import(catalogRow(['name_fa' => 'نام تازه', 'sku' => 'OLD-1', 'usage' => '', 'barcode' => '', 'official_price' => '100000']))
        ->assertImported();

    $product->refresh();
    $variant->refresh();

    expect($product->getNameFa())->toBe('نام تازه')
        ->and($product->getUsage())->toBe('روش قبلی')
        ->and($product->getBrandId())->not->toBeNull()
        ->and($product->getPrimarySubcategoryId())->not->toBeNull()
        ->and($variant->getBarcode())->toBe('111')
        ->and($variant->getOfficialPrice())->toBe(1_000_000);
});

it('refuses a new product without its type or SKU name, and an unknown type', function (): void {
    ProductImporter::test(import: catalogImport($this->admin))
        ->import(catalogRow(['product_type' => '']))
        ->assertHasRowFailure('محصول جدید است؛ این ستون‌ها را پر کنید: نوع محصول.');

    $invalid = ProductImporter::test(import: catalogImport($this->admin))->import(catalogRow(['product_type' => 'خوراکی']));

    expect($invalid->failedRules())->toHaveKey('product_type')
        ->and(Product::query()->count())->toBe(0);
});

it('refuses a row whose name matches several products', function (): void {
    Product::factory()->count(2)->create([Product::COLUMN_NAME_FA => 'ویتامین D3 ۱۰۰۰']);

    ProductImporter::test(import: catalogImport($this->admin))
        ->import(catalogRow())
        ->assertHasRowFailure('چند محصول با این نام هست؛ کد SKU یکی از SKUهای همان محصول را بنویسید.');
});

it('queues the image download with the product\'s URLs and the importing admin', function (): void {
    Queue::fake();

    ProductImporter::test(import: catalogImport($this->admin))
        ->import(catalogRow(['images' => 'https://cdn.example.com/a.jpg، https://cdn.example.com/b.png']))
        ->assertImported();

    Queue::assertPushedOn(ProductImporter::QUEUE, DownloadImportedProductImages::class, fn (DownloadImportedProductImages $job): bool => $job->imageUrls === ['https://cdn.example.com/a.jpg', 'https://cdn.example.com/b.png']
        && $job->adminId === $this->admin->getKey());
});

it('refuses image cells that are not web addresses', function (): void {
    $import = ProductImporter::test(import: catalogImport($this->admin))->import(catalogRow(['images' => 'ftp://cdn.example.com/a.jpg']));

    expect($import->errors()->isNotEmpty())->toBeTrue();
});

it('downloads the images once and tells the admin about the ones that failed', function (): void {
    Queue::fake();
    Notification::fake();
    $product = Product::factory()->create();
    $image = imagecreatetruecolor(1200, 1200);
    ob_start();
    imagepng($image);
    $png = (string) ob_get_clean();
    Http::fake([
        'cdn.example.com/a.png' => Http::response($png, 200, ['Content-Type' => 'image/png']),
        'cdn.example.com/broken.png' => Http::response('', 404),
    ]);

    $job = new DownloadImportedProductImages($product->getId(), ['https://cdn.example.com/a.png', 'https://cdn.example.com/broken.png'], $this->admin->getKey());
    $job->handle(app(ImportProductImages::class));
    $job->handle(app(ImportProductImages::class));

    $gallery = $product->refresh()->getMedia(Product::IMAGE_GALLERY);

    expect($gallery)->toHaveCount(1)
        ->and($gallery->first()?->getCustomProperty(ImportProductImages::PROPERTY_SOURCE_URL))->toBe('https://cdn.example.com/a.png');

    Notification::assertSentToTimes($this->admin, FilamentDatabaseNotification::class, 2);
    Notification::assertSentTo($this->admin, FilamentDatabaseNotification::class, fn (FilamentDatabaseNotification $notification): bool => str_contains((string) ($notification->data['body'] ?? ''), 'broken.png'));
});

it('never downloads from private addresses', function (string $url): void {
    expect(ImportProductImages::isPublic($url))->toBeFalse();
})->with(['http://localhost/a.png', 'http://127.0.0.1/a.png', 'http://10.0.0.5/a.png', 'http://192.168.1.2/a.png']);

it('cannot activate a product without brand or category, and the shop never lists one', function (): void {
    ProductImporter::test(import: catalogImport($this->admin))->import(catalogRow())->assertImported();
    $product = Product::query()->sole();

    expect(fn () => app(UpdateProduct::class)($product, ProductImporter::productData($product, [Product::COLUMN_STATUS => ProductStatus::Active])))
        ->toThrow(ProductChangeRefused::class, 'برند مشخص نیست');

    Product::query()->whereKey($product->getId())->update([Product::COLUMN_STATUS => ProductStatus::Active]);

    expect(Product::query()->active()->count())->toBe(0)
        ->and($product->refresh()->isActive())->toBeFalse();
});

it('shows the import action and lists products missing brand, category or subcategory', function (): void {
    $complete = Product::factory()->create();
    ProductImporter::test(import: catalogImport($this->admin))->import(catalogRow())->assertImported();
    $incomplete = Product::query()->whereNull(Product::COLUMN_BRAND_ID)->sole();

    $this->actingAs($this->admin, 'admin');

    livewire(ListProducts::class)
        ->assertActionVisible('import')
        ->assertSee('تعیین نشده')
        ->filterTable('incomplete')
        ->assertCanSeeTableRecords([$incomplete])
        ->assertCanNotSeeTableRecords([$complete]);
});

it('tells the importing admin after a chunk with failed rows, and not after a clean one', function (): void {
    $import = catalogImport($this->admin)->forceFill(['file_name' => 'catalog.csv', 'file_path' => 'x', 'importer' => ProductImporter::class, 'total_rows' => 100]);
    $import->save();

    (new NotifyAdminOfImportChunk)->handle(new ImportChunkProcessed($import, [], [], 100, 100));
    expect(DatabaseNotification::query()->count())->toBe(0);

    (new NotifyAdminOfImportChunk)->handle(new ImportChunkProcessed($import, [], [], 100, 97));

    $notification = DatabaseNotification::query()->sole();

    expect($notification->notifiable_id)->toBe($this->admin->getKey())
        ->and((string) $notification->data['title'])->toBe('ورود کالا: ۳ ردیف وارد نشد');
});
