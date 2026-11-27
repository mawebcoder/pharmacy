<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Jobs;

use App\Modules\Catalog\Search\ProductSearchIndex;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Rebuilds one chunk of product documents in bulk (ProductSearchIndex::CHUNK at most), on the "search" queue.
 */
final class ReindexProducts implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    /**
     * @param  list<int>  $productIds
     */
    public function __construct(public readonly array $productIds)
    {
        $this->onQueue('search');
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(): void
    {
        ProductSearchIndex::reindex($this->productIds);
    }
}
