<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Jobs;

use App\Modules\Pricing\Actions\CheckPriceImport;
use App\Modules\Pricing\Models\PriceImport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Reads and checks an uploaded supplier price file for the preview, on the «price-import» queue (roadmap D9).
 */
final class ParsePriceImport implements ShouldQueue
{
    use Queueable;

    public const string QUEUE = 'price-import';

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public readonly int $priceImportId)
    {
        $this->onQueue(self::QUEUE);
    }

    public function handle(CheckPriceImport $check): void
    {
        $import = PriceImport::query()->find($this->priceImportId);

        if ($import instanceof PriceImport) {
            $check($import);
        }
    }
}
