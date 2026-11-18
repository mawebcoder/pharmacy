<?php

declare(strict_types=1);

namespace App\Modules\Content\Actions;

use App\Modules\Content\Models\MenuItem;

/**
 * Removes a link from its menu (roadmap S29).
 */
final class DeleteMenuItem
{
    public function __invoke(MenuItem $item): void
    {
        $item->delete();
    }
}
