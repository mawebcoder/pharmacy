<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Tag;
use Illuminate\Support\Facades\DB;

/**
 * Moves a tag to the trash and removes it from its products. Its slug stays reserved while it is in the trash.
 */
final class DeleteTag
{
    public function __invoke(Tag $tag): void
    {
        DB::transaction(function () use ($tag): void {
            $tag->products()->detach();
            $tag->delete();
        });
    }
}
