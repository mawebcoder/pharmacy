<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A tag's name changed, which the documents of its products copy (tags). Raised only when such a field
 * changed; QueueProductReindex rebuilds just those products (owner, 2026-11-27).
 */
final readonly class TagSearchFieldsChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public int $tagId) {}
}
