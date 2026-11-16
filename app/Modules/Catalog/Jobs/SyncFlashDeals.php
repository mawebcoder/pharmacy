<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Jobs;

use App\Modules\Catalog\Actions\SyncFlashDealPrices;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Every minute (roadmap S33): flash deals that started get their price on the SKU, ended ones give it back.
 */
final class SyncFlashDeals implements ShouldQueue
{
    use Queueable;

    public function handle(SyncFlashDealPrices $syncPrices): void
    {
        $syncPrices();
    }
}
