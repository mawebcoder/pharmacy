<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Subcategory;
use App\Modules\Catalog\Search\ProductSearchIndex;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\ParallelTesting;
use Laravel\Scout\EngineManager;
use Meilisearch\Client;
use Meilisearch\Contracts\TasksQuery;

/**
 * Switches search on for this test with its own empty Meilisearch indexes ("test_{process}_products", "…_brands",
 * "…_categories"), so parallel test processes and the development indexes never mix. Normal tests run with
 * SCOUT_DRIVER=null. Brands and categories created afterwards are indexed as they are saved.
 */
function useSearchIndex(): void
{
    $prefix = 'test_'.(ParallelTesting::token() ?: '0').'_';
    config(['scout.driver' => 'meilisearch', 'scout.prefix' => $prefix, 'scout.queue' => false]);
    app(EngineManager::class)->forgetDrivers();

    Artisan::call('scout:sync-index-settings');
    $client = app(Client::class);
    foreach (['products', 'brands', 'categories', 'subcategories'] as $index) {
        $client->index($prefix.$index)->deleteAllDocuments();
    }
    waitForSearchIndex();
}

/**
 * Sends every product to the test index (or removes it when not for sale) and waits until Meilisearch is done.
 */
function indexProductsForSearch(): void
{
    Product::withTrashed()->get()->each(fn (Product $product) => $product->trashed() ? $product->unsearchable() : ProductSearchIndex::refresh($product));
    waitForSearchIndex();
}

function waitForSearchIndex(): void
{
    $client = app(Client::class);
    $indexes = [(new Product)->indexableAs(), (new Brand)->indexableAs(), (new Category)->indexableAs(), (new Subcategory)->indexableAs()];

    for ($attempt = 0; $attempt < 200; $attempt++) {
        $pending = $client->getTasks((new TasksQuery)->setIndexUids($indexes)->setStatuses(['enqueued', 'processing']))->getResults();

        if ($pending === []) {
            return;
        }

        usleep(50_000);
    }

    throw new RuntimeException('Meilisearch did not finish indexing in time.');
}
