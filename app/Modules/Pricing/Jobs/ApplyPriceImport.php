<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Jobs;

use App\Modules\Pricing\Actions\ApplyPriceImport as ApplyPriceImportAction;
use App\Modules\Pricing\Models\PriceImport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Saves a previewed supplier price import, on the «price-import» queue (roadmap D9); re-pricing follows on «pricing».
 */
final class ApplyPriceImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public readonly int $priceImportId)
    {
        $this->onQueue(ParsePriceImport::QUEUE);
    }

    public function handle(ApplyPriceImportAction $apply): void
    {
        $import = PriceImport::query()->find($this->priceImportId);

        if ($import instanceof PriceImport) {
            $apply($import);
        }
    }
}
