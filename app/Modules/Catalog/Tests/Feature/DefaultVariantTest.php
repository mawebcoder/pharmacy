<?php

declare(strict_types=1);

use App\Modules\Catalog\Actions\DeleteProductVariant;
use App\Modules\Catalog\Actions\ReorderProductVariants;
use App\Modules\Catalog\Actions\SetDefaultVariant;
use App\Modules\Catalog\Enums\StockStatus;
use App\Modules\Catalog\Exceptions\ProductChangeRefused;
use App\Modules\Catalog\Filament\Resources\Products\Pages\EditProduct;
use App\Modules\Catalog\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Search\ProductDocument;
use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

/**
 * A product with three variants in admin order: 30-pack, 60-pack, 90-pack.
 */
function threePacks(): Product
{
    $product = Product::factory()->active()->create();

    foreach (['۳۰ عددی', '۶۰ عددی', '۹۰ عددی'] as $sort => $title) {
        ProductVariant::factory()->for($product)->create([ProductVariant::COLUMN_TITLE_FA => $title, ProductVariant::COLUMN_SORT => $sort + 1]);
    }

    return $product->fresh();
}

function variantTitled(Product $product, string $title): ProductVariant
{
    return $product->variants()->where(ProductVariant::COLUMN_TITLE_FA, $title)->sole();
}

function setStock(Product $product, string $title, StockStatus $status): void
{
    variantTitled($product, $title)->setStockStatus($status)->save();
}

function shownTitle(Product $product): ?string
{
    return $product->fresh()->getDefaultVariant()?->getTitleFa();
}

describe('the default SKU', function (): void {
    it('is the admin\'s choice while it is in stock', function (): void {
        $product = threePacks();

        app(SetDefaultVariant::class)($product, variantTitled($product, '۶۰ عددی'));

        expect(shownTitle($product))->toBe('۶۰ عددی');
    });

    it('is the first variant in admin order when none is chosen', function (): void {
        expect(shownTitle(threePacks()))->toBe('۳۰ عددی');
    });

    it('moves to the first in-stock variant in admin order and comes back when the choice is in stock again', function (): void {
        $product = threePacks();
        app(SetDefaultVariant::class)($product, variantTitled($product, '۶۰ عددی'));
        setStock($product, '۳۰ عددی', StockStatus::OutOfStock);

        setStock($product, '۶۰ عددی', StockStatus::OutOfStock);
        $whileOut = shownTitle($product);
        setStock($product, '۶۰ عددی', StockStatus::Available);

        expect($whileOut)->toBe('۹۰ عددی')
            ->and(shownTitle($product))->toBe('۶۰ عددی');
    });

    it('stays the admin\'s choice, out of stock, when nothing is in stock', function (): void {
        $product = threePacks();
        app(SetDefaultVariant::class)($product, variantTitled($product, '۶۰ عددی'));
        $product->variants()->update([ProductVariant::COLUMN_STOCK_STATUS => StockStatus::OutOfStock->value]);

        $default = $product->fresh()->getDefaultVariant();

        expect($default?->getTitleFa())->toBe('۶۰ عددی')
            ->and($default?->isInStock())->toBeFalse();
    });

    it('follows the admin order after a reorder', function (): void {
        $product = threePacks();
        $ids = $product->variants()->pluck(ProductVariant::COLUMN_ID)->all();

        app(ReorderProductVariants::class)($product, [$ids[2], $ids[0], $ids[1]]);

        expect(shownTitle($product))->toBe('۹۰ عددی');
    });

    it('refuses a variant of another product or an inactive one', function (string $case): void {
        $product = threePacks();
        $variant = $case === 'another product'
            ? ProductVariant::factory()->create()
            : ProductVariant::factory()->inactive()->for($product)->create();

        expect(fn () => app(SetDefaultVariant::class)($product, $variant))->toThrow(ProductChangeRefused::class);
    })->with(['another product', 'inactive']);

    it('is cleared when the chosen variant is deleted', function (): void {
        $product = threePacks();
        app(SetDefaultVariant::class)($product, variantTitled($product, '۶۰ عددی'));

        app(DeleteProductVariant::class)(variantTitled($product, '۶۰ عددی'));

        expect($product->fresh()->getDefaultVariantId())->toBeNull()
            ->and(shownTitle($product))->toBe('۳۰ عددی');
    });
});

describe('prices', function (): void {
    it('strikes through the box price, or the price before a sale when there is no box price', function (?int $official, ?int $sale, ?int $compareAt, int $discount): void {
        $variant = ProductVariant::factory()->make([
            ProductVariant::COLUMN_OFFICIAL_PRICE => $official,
            ProductVariant::COLUMN_PRICE => 1_200_000,
            ProductVariant::COLUMN_SALE_PRICE => $sale,
        ]);

        expect($variant->compareAtPrice())->toBe($compareAt)
            ->and($variant->discountPercent())->toBe($discount);
    })->with([
        'box price and sale' => [1_250_000, 1_100_000, 1_250_000, 12],
        'below the box price, no sale' => [1_250_000, null, 1_250_000, 4],
        'at the box price' => [1_200_000, null, null, 0],
        'no box price, on sale' => [null, 900_000, 1_200_000, 25],
        'no box price, no sale' => [null, null, null, 0],
    ]);

    it('puts the default SKU and every active SKU with their prices in the search document', function (): void {
        $product = threePacks();
        variantTitled($product, '۶۰ عددی')->setOfficialPrice(1_250_000)->setPrice(1_200_000)->setSalePrice(1_100_000)->save();
        app(SetDefaultVariant::class)($product, variantTitled($product, '۶۰ عددی'));

        $document = ProductDocument::for($product->fresh()->load(ProductDocument::RELATIONS));

        expect($document['default_variant'])->toMatchArray([
            'title_fa' => '۶۰ عددی', 'price' => 1_200_000, 'sale_price' => 1_100_000, 'compare_at_price' => 1_250_000,
            'final_price' => 1_100_000, 'discount_pct' => 12, 'in_stock' => true,
        ])
            ->and(array_column($document['variants'], 'title_fa'))->toBe(['۳۰ عددی', '۶۰ عددی', '۹۰ عددی'])
            ->and($document['price'])->toBe(1_100_000)
            ->and($document['discount_pct'])->toBe(12);
    });
});

describe('in the admin', function (): void {
    beforeEach(function (): void {
        Filament::setCurrentPanel('admin');
        $this->seed(RolesSeeder::class);
        $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::ContentEditor)->create(), 'admin');
    });

    it('makes a variant the default from the variants table', function (): void {
        $product = threePacks();
        $sixty = variantTitled($product, '۶۰ عددی');

        livewire(VariantsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callAction(TestAction::make('makeDefault')->table($sixty))
            ->assertNotified('SKU پیش‌فرض تغییر کرد.');

        expect($product->fresh()->getDefaultVariantId())->toBe($sixty->getId());
    });

    it('saves a drag and drop of the variants', function (): void {
        $product = threePacks();
        $ids = $product->variants()->pluck(ProductVariant::COLUMN_ID)->all();

        livewire(VariantsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->call('reorderTable', [(string) $ids[1], (string) $ids[2], (string) $ids[0]]);

        expect($product->variants()->pluck(ProductVariant::COLUMN_ID)->all())->toBe([$ids[1], $ids[2], $ids[0]]);
    });

    it('lets operators neither choose the default nor reorder', function (): void {
        $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::Operator)->create(), 'admin');
        $product = threePacks();
        $ids = $product->variants()->pluck(ProductVariant::COLUMN_ID)->all();

        livewire(VariantsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->assertActionHidden(TestAction::make('makeDefault')->table(variantTitled($product, '۶۰ عددی')))
            ->call('reorderTable', [(string) $ids[2], (string) $ids[1], (string) $ids[0]])
            ->assertForbidden();

        expect($product->variants()->pluck(ProductVariant::COLUMN_ID)->all())->toBe($ids);
    });
});
