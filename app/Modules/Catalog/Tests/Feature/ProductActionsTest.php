<?php

declare(strict_types=1);

use App\Modules\Catalog\Actions\CreateProduct;
use App\Modules\Catalog\Actions\DeleteProduct;
use App\Modules\Catalog\Actions\DeleteProductVariant;
use App\Modules\Catalog\Actions\LinkProducts;
use App\Modules\Catalog\Actions\RestoreProduct;
use App\Modules\Catalog\Actions\SaveProductVariant;
use App\Modules\Catalog\Actions\UpdateProduct;
use App\Modules\Catalog\Data\ProductData;
use App\Modules\Catalog\Data\ProductVariantData;
use App\Modules\Catalog\Enums\ProductRelationType;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\Exceptions\ProductChangeRefused;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Media\Actions\AttachImage;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Storage::fake('media');
});

function productData(Product $product, array $overrides = []): ProductData
{
    return ProductData::fromArray([
        Product::COLUMN_NAME_FA => $product->getNameFa(),
        Product::COLUMN_NAME_EN => $product->getNameEn(),
        Product::COLUMN_BRAND_ID => $product->getBrandId(),
        Product::COLUMN_PRIMARY_CATEGORY_ID => $product->getPrimaryCategoryId(),
        Product::COLUMN_PRODUCT_TYPE => $product->getProductType(),
        Product::COLUMN_REQUIRES_PRESCRIPTION => $product->requiresPrescription(),
        Product::COLUMN_STATUS => $product->getStatus(),
        ...$overrides,
    ]);
}

function variantData(array $overrides = []): ProductVariantData
{
    return ProductVariantData::fromArray([
        ProductVariant::COLUMN_TITLE_FA => '۵۰ میلی‌لیتر',
        ProductVariant::COLUMN_OFFICIAL_PRICE => 4_850_000,
        ProductVariant::COLUMN_PRICE => 4_600_000,
        ProductVariant::COLUMN_STOCK_STATUS => 'available',
        ...$overrides,
    ]);
}

/**
 * A product that passes every activation check: an active variant with a price and a photo.
 */
function readyProduct(array $state = []): Product
{
    $product = Product::factory()->withVariants()->create($state);
    $photo = app(AttachImage::class)($product, Product::IMAGE_GALLERY, testImage(1200, 1200, 'jpg'));
    expect($photo->getPresetName())->toBe('product');

    return $product->fresh();
}

describe('creating', function (): void {
    it('creates a draft product with its extra categories and a slug from the English name', function (): void {
        $brand = Brand::factory()->create();
        [$primary, $extra] = Category::factory()->count(2)->create();

        $product = app(CreateProduct::class)(ProductData::fromArray([
            Product::COLUMN_NAME_FA => 'کرم مرطوب‌کننده اوره ۱۰٪',
            Product::COLUMN_NAME_EN => 'UreaRepair Plus 10% Cream',
            Product::COLUMN_BRAND_ID => $brand->getId(),
            Product::COLUMN_PRIMARY_CATEGORY_ID => $primary->getId(),
            'category_ids' => [$extra->getId()],
            Product::COLUMN_PRODUCT_TYPE => 'cosmetic',
        ]));

        expect($product)
            ->getSlug()->toBe('urearepair-plus-10-cream')
            ->getStatus()->toBe(ProductStatus::Draft)
            ->and($product->categories()->pluck('categories.id')->all())->toBe([$extra->getId()]);
    });

    it('always needs a prescription for a prescription medicine', function (): void {
        $product = Product::factory()->create();

        $updated = app(UpdateProduct::class)($product, productData($product, [Product::COLUMN_PRODUCT_TYPE => 'rx', Product::COLUMN_REQUIRES_PRESCRIPTION => false]));

        expect($updated->requiresPrescription())->toBeTrue();
    });
});

describe('activating', function (): void {
    it('activates a product that has a brand, category, active priced variant and photo', function (): void {
        $product = readyProduct();

        $activated = app(UpdateProduct::class)($product, productData($product, [Product::COLUMN_STATUS => 'active']));

        expect($activated->fresh()->getStatus())->toBe(ProductStatus::Active);
    });

    it('refuses to activate a product that is missing something', function (Closure $prepare, string $reason): void {
        $product = $prepare();

        expect(fn () => app(UpdateProduct::class)($product, productData($product, [Product::COLUMN_STATUS => 'active'])))
            ->toThrow(ProductChangeRefused::class, $reason);

        expect($product->fresh()->getStatus())->toBe(ProductStatus::Draft);
    })->with([
        'prescription medicine' => [fn (): Product => readyProduct([Product::COLUMN_PRODUCT_TYPE => ProductType::Rx, Product::COLUMN_REQUIRES_PRESCRIPTION => true]), 'داروهای نیازمند نسخه'],
        'needs a prescription' => [fn (): Product => readyProduct([Product::COLUMN_REQUIRES_PRESCRIPTION => true]), 'داروهای نیازمند نسخه'],
        'no variant' => [fn (): Product => Product::factory()->create(), 'تنوع فعال'],
        'only inactive variants' => [fn (): Product => Product::factory()->has(ProductVariant::factory()->inactive(), 'variants')->create(), 'تنوع فعال'],
        'no photo' => [fn (): Product => Product::factory()->withVariants()->create(), 'تصویر'],
    ]);

    it('sells OTC medicines (owner decision, 2026-11-07)', function (): void {
        $product = readyProduct([Product::COLUMN_PRODUCT_TYPE => ProductType::Otc]);

        $activated = app(UpdateProduct::class)($product, productData($product, [Product::COLUMN_STATUS => 'active']));

        expect($activated->isActive())->toBeTrue();
    });

    it('stops OTC sales when OTC is listed as unsellable', function (): void {
        config(['catalog.unsellable_product_types' => ['rx', 'otc']]);
        $product = readyProduct([Product::COLUMN_PRODUCT_TYPE => ProductType::Otc]);

        expect(fn () => app(UpdateProduct::class)($product, productData($product, [Product::COLUMN_STATUS => 'active'])))
            ->toThrow(ProductChangeRefused::class);
    });

    it('refuses to turn an active product into a prescription medicine', function (): void {
        $product = readyProduct([Product::COLUMN_STATUS => ProductStatus::Active]);

        expect(fn () => app(UpdateProduct::class)($product, productData($product, [Product::COLUMN_PRODUCT_TYPE => 'rx'])))
            ->toThrow(ProductChangeRefused::class);

        expect($product->fresh()->getProductType())->toBe(ProductType::Cosmetic);
    });
});

describe('variants', function (): void {
    it('creates two variants with generated SKUs from the brand name', function (): void {
        $product = Product::factory()->for(Brand::factory()->state([Brand::COLUMN_NAME_EN => 'Eucerin']))->create();

        $first = app(SaveProductVariant::class)($product, variantData());
        $second = app(SaveProductVariant::class)($product, variantData([ProductVariant::COLUMN_TITLE_FA => '۲۰۰ میلی‌لیتر']));

        expect($first->getSku())->toBe(sprintf('EUC-%06d-01', $product->getId()))
            ->and($second->getSku())->toBe(sprintf('EUC-%06d-02', $product->getId()))
            ->and($product->variants()->count())->toBe(2);
    });

    it('enforces the price rules', function (string $type, array $prices, string $reason): void {
        $product = Product::factory()->create([Product::COLUMN_PRODUCT_TYPE => ProductType::from($type)]);

        expect(fn () => app(SaveProductVariant::class)($product, variantData($prices)))->toThrow(ProductChangeRefused::class, $reason);
    })->with([
        'sale price not below price' => ['cosmetic', [ProductVariant::COLUMN_SALE_PRICE => 4_600_000], 'قیمت حراج'],
        'medicine above the official price' => ['otc', [ProductVariant::COLUMN_PRICE => 5_000_000], 'قیمت مصوب'],
        'medicine without an official price' => ['otc', [ProductVariant::COLUMN_OFFICIAL_PRICE => null], 'قیمت مصوب'],
    ]);

    it('lets cosmetics be priced above the box price', function (): void {
        $product = Product::factory()->create();

        $variant = app(SaveProductVariant::class)($product, variantData([ProductVariant::COLUMN_PRICE => 5_000_000]));

        expect($variant->getPrice())->toBe(5_000_000);
    });

    it('marks a variant near expiry from its expiry date', function (string $daysAhead, bool $near): void {
        $product = Product::factory()->create();

        $variant = app(SaveProductVariant::class)($product, variantData([ProductVariant::COLUMN_EXPIRY_DATE => now()->addDays((int) $daysAhead)->toDateString()]));

        expect($variant->isNearExpiry())->toBe($near);
    })->with([
        'in 30 days' => ['30', true],
        'in 200 days' => ['200', false],
    ]);

    it('keeps the last active variant of an active product', function (): void {
        $product = readyProduct([Product::COLUMN_STATUS => ProductStatus::Active]);
        $variant = $product->variants()->sole();

        expect(fn () => app(DeleteProductVariant::class)($variant))->toThrow(ProductChangeRefused::class)
            ->and(fn () => app(SaveProductVariant::class)($product, variantData([ProductVariant::COLUMN_IS_ACTIVE => false]), $variant))->toThrow(ProductChangeRefused::class);

        expect($variant->fresh())->trashed()->toBeFalse()->isActive()->toBeTrue();
    });
});

describe('deleting and links', function (): void {
    it('moves a product with its variants to the trash and brings them back hidden', function (): void {
        $product = Product::factory()->withVariants(2)->active()->create();

        app(DeleteProduct::class)($product);
        expect(ProductVariant::onlyTrashed()->count())->toBe(2);

        app(RestoreProduct::class)($product->fresh());
        expect($product->fresh())
            ->trashed()->toBeFalse()
            ->getStatus()->toBe(ProductStatus::Hidden)
            ->and(ProductVariant::query()->count())->toBe(2);
    });

    it('links related products once and never to themselves', function (): void {
        [$product, $other] = Product::factory()->count(2)->create();

        $first = app(LinkProducts::class)($product, $other->getId(), ProductRelationType::Alternative);
        $again = app(LinkProducts::class)($product, $other->getId(), ProductRelationType::Alternative);

        expect($again->getId())->toBe($first->getId())
            ->and($product->productRelations()->count())->toBe(1)
            ->and(fn () => app(LinkProducts::class)($product, $product->getId(), ProductRelationType::Related))->toThrow(ProductChangeRefused::class);
    });
});
