<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Data;

/**
 * One choice in a filter group, with how many products it would show.
 */
final readonly class FacetOption
{
    public function __construct(
        public int $id,
        public string $label,
        public int $count,
        public bool $selected,
    ) {}
}
