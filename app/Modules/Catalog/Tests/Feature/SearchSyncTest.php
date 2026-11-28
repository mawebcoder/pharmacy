<?php

declare(strict_types=1);

use App\Modules\Catalog\Actions\UpdateBrand;
use App\Modules\Catalog\Actions\UpdateProduct;
use App\Modules\Catalog\Data\BrandData;
use App\Modules\Catalog\Data\ProductData;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Events\BrandSearchFieldsChanged;
use App\Modules\Catalog\Jobs\ReindexProducts;
use App\Modules\Catalog\Listeners\QueueProductReindex;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Tag;
use App\Modules\Catalog\Search\ProductSearchIndex;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Meilisearch\Client;
use Meilisearch\Exceptions\ApiException;

function productDocument(int $productId): ?array
{
    try {
        return app(Client::class)->index((new Product)->indexableAs())->getDocument($productId);
    } catch (ApiException) {
        return null;
    }
}

describe('events', function (): void {
    it('raises a brand event only when the name changes, not for a description change', function (): void {
        Event::fake([BrandSearchFieldsChanged::class]);
        $brand = Brand::factory()->create([Brand::COLUMN_NAME_FA => 'اوسرین']);
        $data = fn (string $name, string $description): BrandData => BrandData::fromArray([
            Brand::COLUMN_NAME_FA => $name, Brand::COLUMN_NAME_EN => $brand->getNameEn(), Brand::COLUMN_SLUG => $brand->getSlug(),
            Brand::COLUMN_DESCRIPTION => $description, Brand::COLUMN_IS_ACTIVE => true,
        ]);

        (void) app(UpdateBrand::class)($brand, $data('اوسرین', 'توضیح تازه'));
        Event::assertNotDispatched(BrandSearchFieldsChanged::class);

        (void) app(UpdateBrand::class)($brand, $data('اوسرین آلمان', 'توضیح تازه'));
        Event::assertDispatched(BrandSearchFieldsChanged::class, fn (BrandSearchFieldsChanged $event): bool => $event->brandId === $brand->getId());
    });

    it('queues one bulk job with just the renamed brand\'s products', function (): void {
        config(['scout.queue' => true]);
        Queue::fake();
        $brand = Brand::factory()->create();
        $own = Product::factory()->for($brand)->count(3)->create()->modelKeys();
        Product::factory()->count(2)->create();

        app(QueueProductReindex::class)->handle(new BrandSearchFieldsChanged($brand->getId()));

        Queue::assertPushed(ReindexProducts::class, 1);
        Queue::assertPushed(ReindexProducts::class, fn (ReindexProducts $job): bool => $job->productIds === array_map(intval(...), $own) && $job->queue === 'search');
    });
});

describe('the index', function (): void {
    beforeEach(fn () => useSearchIndex());

    it('indexes every product that is not deleted, marking hidden ones not visible, and removes deleted ones', function (): void {
        $shown = Product::factory()->active()->withVariants()->create();
        $draft = Product::factory()->withVariants()->create([Product::COLUMN_STATUS => ProductStatus::Draft]);
        $deleted = Product::factory()->active()->withVariants()->create();
        ProductSearchIndex::reindex([$shown->getId(), $draft->getId(), $deleted->getId()]);
        waitForSearchIndex();

        $deleted->delete();
        ProductSearchIndex::reindex([$deleted->getId()]);
        waitForSearchIndex();

        expect(productDocument($shown->getId())['is_visible'] ?? null)->toBeTrue()
            ->and(productDocument($draft->getId())['is_visible'] ?? null)->toBeFalse()
            ->and(productDocument($deleted->getId()))->toBeNull();
    });

    it('rebuilds a chunk of products in a fixed number of queries', function (int $products): void {
        $ids = Product::factory()->active()->withVariants()->count($products)->create()->modelKeys();

        DB::enableQueryLog();
        ProductSearchIndex::reindex($ids);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        expect($queries)->toBeLessThan(20);
    })->with([3, 12]);

    it('puts the tags saved with the product into its document', function (): void {
        $product = Product::factory()->active()->withVariants()->create();
        $tag = Tag::factory()->create([Tag::COLUMN_NAME_FA => 'ضدآفتاب']);
        $data = ProductData::fromArray([...$product->attributesToArray(), Product::COLUMN_STATUS => ProductStatus::Hidden, 'tag_ids' => [$tag->getId()]]);

        (void) app(UpdateProduct::class)($product, $data);
        waitForSearchIndex();

        expect(productDocument($product->getId())['tag_ids'] ?? [])->toBe([$tag->getId()]);
    });

    it('reconciles: removes documents whose record is gone and adds the missing ones', function (): void {
        $product = Product::factory()->active()->withVariants()->create();
        $index = app(Client::class)->index((new Product)->indexableAs());
        $index->addDocuments([['id' => 999_999, 'name_fa' => 'رکورد پاک‌شده', 'is_visible' => true]]);
        waitForSearchIndex();

        $this->artisan('search:reconcile')->assertSuccessful();
        waitForSearchIndex();

        expect(productDocument(999_999))->toBeNull()
            ->and(productDocument($product->getId()))->not->toBeNull();
    });
});

it('reconciles the search indexes every night at 04:00 Tehran time', function (): void {
    $event = collect(app(Schedule::class)->events())->first(fn (ScheduledEvent $event): bool => str_contains((string) $event->command, 'search:reconcile'));

    expect($event?->expression)->toBe('0 4 * * *')->and($event?->timezone)->toBe('Asia/Tehran');
});
