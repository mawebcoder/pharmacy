<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A subcategory's name changed, which the documents of its products copy (category_names). Raised only when such a field
 * changed; QueueProductReindex rebuilds just those products (owner, 2026-11-27).
 */
final readonly class SubcategorySearchFieldsChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public int $subcategoryId) {}
}
