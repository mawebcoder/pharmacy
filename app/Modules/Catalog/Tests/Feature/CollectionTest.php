<?php

declare(strict_types=1);

use App\Modules\Catalog\Actions\AddProductsToCollection;
use App\Modules\Catalog\Actions\DeleteCollection;
use App\Modules\Catalog\Actions\RemoveProductFromCollection;
use App\Modules\Catalog\Actions\ReorderCollectionProducts;
use App\Modules\Catalog\Actions\SaveCollection;
use App\Modules\Catalog\Data\CollectionData;
use App\Modules\Catalog\Data\ListingCriteria;
use App\Modules\Catalog\Enums\ListingSort;
use App\Modules\Catalog\Enums\StockStatus;
use App\Modules\Catalog\Exceptions\CollectionChangeRefused;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductCollection;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Queries\CollectionProducts;
use App\Modules\Catalog\Queries\ProductListing;
use Meilisearch\Client;

/**
 * An active product with one available variant, named for the assertions.
 */
function collectionProduct(string $name, StockStatus $stock = StockStatus::Available): Product
{
    $product = Product::factory()->active()->create([Product::COLUMN_NAME_FA => $name]);
    ProductVariant::factory()->for($product)->create([ProductVariant::COLUMN_STOCK_STATUS => $stock]);

    return $product->fresh();
}

/**
 * @param  list<Product>  $products  in the collection's order
 */
function collectionWith(array $products, ?ProductCollection $collection = null): ProductCollection
{
    $collection ??= ProductCollection::factory()->create();
    app(AddProductsToCollection::class)($collection, array_map(fn (Product $product): int => $product->getId(), $products));

    return $collection;
}

function popularCollection(): ProductCollection
{
    return ProductCollection::query()->where(ProductCollection::COLUMN_SLUG, ProductCollection::POPULAR_SLUG)->sole();
}

describe('saving and deleting', function (): void {
    it('makes the slug from the English name', function (): void {
        $collection = app(SaveCollection::class)(CollectionData::fromArray([
            ProductCollection::COLUMN_NAME_FA => 'ضدآفتاب‌های تابستان',
            ProductCollection::COLUMN_NAME_EN => 'Summer Sunscreens',
        ]));

        expect($collection->getSlug())->toBe('summer-sunscreens');
    });

    it('keeps the slug of «محصولات محبوب» when the admin changes it', function (): void {
        $saved = app(SaveCollection::class)(CollectionData::fromArray([
            ProductCollection::COLUMN_NAME_FA => 'پرفروش‌ها',
            ProductCollection::COLUMN_SLUG => 'best-sellers',
        ]), popularCollection());

        expect($saved)->getSlug()->toBe('popular')->getNameFa()->toBe('پرفروش‌ها');
    });

    it('refuses to delete «محصولات محبوب»', function (): void {
        expect(fn () => app(DeleteCollection::class)(popularCollection()))->toThrow(CollectionChangeRefused::class);

        expect(popularCollection()->trashed())->toBeFalse();
    });

    it('moves a collection to the trash and takes its products out of it', function (): void {
        $collection = collectionWith([collectionProduct('کرم')]);

        app(DeleteCollection::class)($collection);

        expect($collection->fresh()->trashed())->toBeTrue()
            ->and($collection->products()->count())->toBe(0);
    });
});

describe('products of a collection', function (): void {
    it('adds products at the end, once each, and keeps the admin order', function (): void {
        [$cream, $serum, $shampoo] = [collectionProduct('کرم'), collectionProduct('سرم'), collectionProduct('شامپو')];
        $collection = collectionWith([$serum, $cream]);

        app(AddProductsToCollection::class)($collection, [$cream->getId(), $shampoo->getId(), $shampoo->getId()]);

        expect($collection->products()->get()->map->getNameFa()->all())->toBe(['سرم', 'کرم', 'شامپو']);
    });

    it('saves a new order and removes a product', function (): void {
        [$cream, $serum, $shampoo] = [collectionProduct('کرم'), collectionProduct('سرم'), collectionProduct('شامپو')];
        $collection = collectionWith([$cream, $serum, $shampoo]);

        app(ReorderCollectionProducts::class)($collection, [$shampoo->getId(), $cream->getId(), $serum->getId()]);
        app(RemoveProductFromCollection::class)($collection, $cream);

        expect($collection->products()->get()->map->getNameFa()->all())->toBe(['شامپو', 'سرم']);
    });

    it('gives at most 6 in-stock products for sale, in the admin order', function (): void {
        $products = [
            collectionProduct('ناموجود', StockStatus::OutOfStock),
            Product::factory()->withVariants()->create([Product::COLUMN_NAME_FA => 'پیش‌نویس']),
            ...array_map(fn (int $number): Product => collectionProduct("محصول {$number}"), range(1, 7)),
        ];
        collectionWith($products, popularCollection());

        expect(app(CollectionProducts::class)->popular()->map->getNameFa()->all())
            ->toBe(['محصول 1', 'محصول 2', 'محصول 3', 'محصول 4', 'محصول 5', 'محصول 6']);
    });

    it('gives no products for a hidden collection', function (): void {
        $collection = collectionWith([collectionProduct('کرم')], ProductCollection::factory()->ended()->create());

        expect(app(CollectionProducts::class)->forCollection($collection->getId()))->toBeEmpty();
    });
});

describe('storefront', function (): void {
    it('lists visible collections that have products for sale', function (): void {
        $product = collectionProduct('کرم');
        collectionWith([$product], ProductCollection::factory()->create([ProductCollection::COLUMN_NAME_FA => 'ضدآفتاب‌ها']));
        collectionWith([$product], ProductCollection::factory()->inactive()->create([ProductCollection::COLUMN_NAME_FA => 'کالکشن خاموش']));
        collectionWith([$product], ProductCollection::factory()->ended()->create([ProductCollection::COLUMN_NAME_FA => 'کالکشن تمام‌شده']));
        collectionWith([Product::factory()->withVariants()->create()], ProductCollection::factory()->create([ProductCollection::COLUMN_NAME_FA => 'فقط پیش‌نویس']));
        ProductCollection::factory()->create([ProductCollection::COLUMN_NAME_FA => 'کالکشن خالی']);

        $this->get(route('collections.index'))
            ->assertOk()
            ->assertSee('ضدآفتاب‌ها')
            ->assertDontSee('محصولات محبوب')
            ->assertDontSee('کالکشن خاموش')
            ->assertDontSee('کالکشن تمام‌شده')
            ->assertDontSee('فقط پیش‌نویس')
            ->assertDontSee('کالکشن خالی');
    });

    it('lists only the collection products in the admin order', function (): void {
        collectionProduct('بیرون از کالکشن');
        $collection = collectionWith([collectionProduct('سرم'), collectionProduct('کرم')]);

        $this->get(route('collections.show', $collection))
            ->assertOk()
            ->assertSeeInOrder(['سرم', 'کرم'])
            ->assertDontSee('بیرون از کالکشن');
    });

    it('does not show a hidden collection', function (Closure $makeCollection): void {
        $this->get(route('collections.show', $makeCollection()))->assertNotFound();
    })->with([
        'inactive' => [fn () => ProductCollection::factory()->inactive()->create()],
        'schedule ended' => [fn () => ProductCollection::factory()->ended()->create()],
        'starts tomorrow' => [fn () => ProductCollection::factory()->create([ProductCollection::COLUMN_STARTS_AT => now()->addDay()])],
    ]);

    it('orders a collection by the admin order through Meilisearch', function (): void {
        useSearchIndex();
        [$first, $second, $third] = [collectionProduct('اول'), collectionProduct('دوم'), collectionProduct('سوم')];
        $collection = collectionWith([$second, $third, $first]);
        indexProductsForSearch();

        $listed = app(ProductListing::class)->get(new ListingCriteria(collectionId: $collection->getId(), sort: ListingSort::Featured, perPage: 2));

        expect($listed->products->getCollection()->map->getNameFa()->all())->toBe(['دوم', 'سوم'])
            ->and($listed->products->total())->toBe(3);
    });

    it('keeps the admin order when Meilisearch is down', function (): void {
        useSearchIndex();
        config(['scout.meilisearch.host' => 'http://127.0.0.1:1']);
        app()->forgetInstance(Client::class);
        $collection = collectionWith([collectionProduct('دوم'), collectionProduct('اول')]);

        $listed = app(ProductListing::class)->get(new ListingCriteria(collectionId: $collection->getId(), sort: ListingSort::Featured));

        expect($listed->products->getCollection()->map->getNameFa()->all())->toBe(['دوم', 'اول']);
    });
});

it('redirects the first short addresses permanently to the new ones', function (string $old, string $new): void {
    $this->get($old)->assertMovedPermanently()->assertRedirect($new);
})->with([
    'brands list' => ['/b', '/brands'],
    'brand' => ['/b/eucerin', '/brands/eucerin'],
    'category' => ['/c/skin', '/categories/skin'],
    'product' => ['/p/urea-cream', '/products/urea-cream'],
]);
