<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Search\ProductSearchIndex;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\ParallelTesting;
use Laravel\Scout\EngineManager;
use Meilisearch\Client;
use Meilisearch\Contracts\TasksQuery;

/**
 * Switches product search on for this test with its own empty Meilisearch index ("test_{process}_products"), so
 * parallel test processes and the development index never mix. Normal tests run with SCOUT_DRIVER=null.
 */
function useSearchIndex(): void
{
    $prefix = 'test_'.(ParallelTesting::token() ?: '0').'_';
    config(['scout.driver' => 'meilisearch', 'scout.prefix' => $prefix, 'scout.queue' => false]);
    app(EngineManager::class)->forgetDrivers();

    Artisan::call('scout:sync-index-settings');
    $client = app(Client::class);
    $client->index($prefix.'products')->deleteAllDocuments();
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
    $index = (new Product)->indexableAs();

    for ($attempt = 0; $attempt < 200; $attempt++) {
        $pending = $client->getTasks((new TasksQuery)->setIndexUids([$index])->setStatuses(['enqueued', 'processing']))->getResults();

        if ($pending === []) {
            return;
        }

        usleep(50_000);
    }

    throw new RuntimeException('Meilisearch did not finish indexing in time.');
}
