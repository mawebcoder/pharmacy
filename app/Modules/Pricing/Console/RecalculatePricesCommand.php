<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Console;

use App\Modules\Pricing\Enums\PriceChangeReason;
use App\Modules\Pricing\Jobs\RecalculateVariantPrices;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Queues a re-price of every SKU on the «pricing» queue (roadmap D3.2, D9): once after the S14/S15 deploy, so SKUs
 * priced by hand before follow the engine (active supplier, else the printed price), and nightly as a safety net.
 */
final class RecalculatePricesCommand extends Command
{
    private const int BATCH = 500;

    protected $signature = 'pricing:recalculate';

    protected $description = 'Queue a price recalculation of every SKU (supplier price + commission, else the printed price)';

    public function handle(): int
    {
        $queued = 0;

        DB::table('product_variants')->orderBy('id')->chunkById(self::BATCH, function ($variants) use (&$queued): void {
            $ids = array_values(array_map(fn (object $variant): int => (int) $variant->id, $variants->all()));
            RecalculateVariantPrices::dispatch($ids, PriceChangeReason::Recalculated);
            $queued += count($ids);
        });

        $this->components->info("{$queued} SKU برای محاسبه دوباره قیمت در صف «pricing» قرار گرفت.");

        return self::SUCCESS;
    }
}
