<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Console;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Subcategory;
use App\Modules\Catalog\Models\Tag;
use App\Modules\Catalog\Search\ProductSearchIndex;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Meilisearch\Client;
use Meilisearch\Contracts\DocumentsQuery;
use Meilisearch\Exceptions\ApiException;

/**
 * The nightly safety net for search (owner, 2026-11-27; nightly since 2026-11-28): for the products, brands, categories, subcategories and tags indexes
 * it deletes documents whose record is gone and re-pushes every record that is not deleted (products in bulk jobs of
 * ProductSearchIndex::CHUNK), so a lost or stale update is fixed by the next morning (and right after every deploy). Scheduled in CatalogServiceProvider.
 *
 *   php artisan search:reconcile
 */
final class ReconcileSearchIndexesCommand extends Command
{
    private const int PAGE = 1000;

    protected $signature = 'search:reconcile';

    protected $description = 'Delete search documents whose record is gone and re-push every product, brand, category and subcategory';

    public function handle(Client $meilisearch): int
    {
        if (config('scout.driver') !== 'meilisearch') {
            $this->components->info('Search is not on Meilisearch here; nothing to reconcile.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ([Product::class, Brand::class, Category::class, Subcategory::class, Tag::class] as $class) {
            /** @var Product|Brand|Category|Subcategory|Tag $model */
            $model = new $class;
            $index = $meilisearch->index($model->indexableAs());
            $expected = array_map(intval(...), $class::query()->pluck($model->getKeyName())->all());
            $indexed = $this->indexedIds($meilisearch, $model);
            $extra = array_values(array_diff($indexed, $expected));
            $missing = array_values(array_diff($expected, $indexed));

            if ($extra !== []) {
                $index->deleteDocuments($extra);
            }

            $model instanceof Product ? ProductSearchIndex::queue($expected) : $class::makeAllSearchable();

            if ($extra !== [] || $missing !== []) {
                Log::info('Search index reconciled.', ['index' => $model->indexableAs(), 'removed' => count($extra), 'missing' => count($missing)]);
            }

            $rows[] = [$model->indexableAs(), count($expected), count($indexed), count($extra), count($missing)];
        }

        $this->table(['Index', 'Records', 'Documents before', 'Removed', 'Were missing'], $rows);

        return self::SUCCESS;
    }

    /**
     * Every document id in the model's index (an index that does not exist yet has none).
     *
     * @return list<int>
     */
    private function indexedIds(Client $meilisearch, Product|Brand|Category|Subcategory|Tag $model): array
    {
        $ids = [];
        $offset = 0;

        try {
            do {
                $page = $meilisearch->index($model->indexableAs())->getDocuments((new DocumentsQuery)->setFields(['id'])->setLimit(self::PAGE)->setOffset($offset));

                foreach ($page->getResults() as $document) {
                    $ids[] = (int) $document['id'];
                }

                $offset += self::PAGE;
            } while ($offset < $page->getTotal());
        } catch (ApiException $exception) {
            if ($exception->httpStatus !== 404) {
                throw $exception;
            }
        }

        return $ids;
    }
}
