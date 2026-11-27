<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * These products' search documents are out of date: the product itself, its links, SKUs, prices, stock, flash deals
 * or collections changed, or it was deleted or restored. Dispatched after the transaction commits, so the rebuild
 * sees the saved links; QueueProductReindex rebuilds them in bulk (owner, 2026-11-27).
 */
final readonly class ProductsSearchFieldsChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  list<int>  $productIds
     */
    public function __construct(public array $productIds) {}
}
