<?php

declare(strict_types=1);

namespace App\Modules\Content\Actions;

use App\Modules\Content\Models\Banner;
use Illuminate\Support\Facades\DB;

/**
 * Moves a banner to the trash and takes it out of every place it is shown.
 */
final class DeleteBanner
{
    public function __invoke(Banner $banner): void
    {
        DB::transaction(function () use ($banner): void {
            $banner->placements()->get()->each->delete();
            $banner->delete();
        });
    }
}
