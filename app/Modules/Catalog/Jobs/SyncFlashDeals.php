<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Jobs;

use App\Modules\Catalog\Actions\SyncFlashDealPrices;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Puts live flash deal prices on their SKUs in the background (roadmap S33; owner, 2026-11-17: all price work runs in
 * the background), on the «pricing» queue: every minute for deals that started or ended, and after a deal is saved or
 * deleted or its units are reserved or released, for those SKUs only.
 */
final class SyncFlashDeals implements ShouldQueue
{
    use Queueable;

    public const string QUEUE = 'pricing';

    /**
     * @param  list<int>|null  $variantIds  null: every SKU
     */
    public function __construct(public ?array $variantIds = null)
    {
        $this->onQueue(self::QUEUE);
    }

    public function handle(SyncFlashDealPrices $syncPrices): void
    {
        $syncPrices($this->variantIds);
    }
}
