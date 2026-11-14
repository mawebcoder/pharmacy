<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Actions;

use App\Modules\Pricing\Enums\PriceImportStatus;
use App\Modules\Pricing\Exceptions\PriceImportRefused;
use App\Modules\Pricing\Jobs\ApplyPriceImport;
use App\Modules\Pricing\Models\PriceImport;
use App\Modules\Pricing\Models\PriceImportRow;
use Illuminate\Support\Facades\DB;

/**
 * The admin applies a previewed import (roadmap D13.9): it is marked «در حال اعمال» and saved in the background on
 * the «price-import» queue. Only a previewed import with rows to change can be applied, once.
 */
final class ConfirmPriceImport
{
    /**
     * @throws PriceImportRefused
     */
    public function __invoke(PriceImport $import): void
    {
        DB::transaction(function () use ($import): void {
            $locked = PriceImport::query()->lockForUpdate()->findOrFail($import->getId());

            if ($locked->getStatus() !== PriceImportStatus::Previewed) {
                throw PriceImportRefused::notPreviewed();
            }

            if (! $locked->rows()->whereNull(PriceImportRow::COLUMN_ERROR)->where(PriceImportRow::COLUMN_CHANGES, true)->exists()) {
                throw PriceImportRefused::nothingToApply();
            }

            $locked->setStatus(PriceImportStatus::Applying)->save();
            ApplyPriceImport::dispatch($locked->getId())->afterCommit();
        });

        $import->refresh();
    }
}
