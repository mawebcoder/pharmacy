<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Actions;

use App\Modules\Pricing\Data\ProviderPriceData;
use App\Modules\Pricing\Enums\PriceChangeReason;
use App\Modules\Pricing\Enums\PriceImportStatus;
use App\Modules\Pricing\Models\PriceImport;
use App\Modules\Pricing\Models\PriceImportRow;
use App\Modules\Pricing\Models\ProviderPrice;
use Illuminate\Support\Facades\Storage;

/**
 * Applies a previewed supplier price import (roadmap D13.9): every checked row that changes something is saved
 * through SaveProviderPrice (supplier price history, re-pricing on the «pricing» queue, search follows). Rows with
 * problems are skipped; supplier prices missing from the file are left as they are.
 */
final readonly class ApplyPriceImport
{
    public function __construct(private SaveProviderPrice $saveProviderPrice) {}

    public function __invoke(PriceImport $import): void
    {
        if ($import->getStatus() !== PriceImportStatus::Applying) {
            return;
        }

        $updated = 0;
        $rows = $import->rows()->whereNull(PriceImportRow::COLUMN_ERROR)->where(PriceImportRow::COLUMN_CHANGES, true)->orderBy(PriceImportRow::COLUMN_ROW_NUMBER)->lazy(200);

        foreach ($rows as $row) {
            $existing = ProviderPrice::query()
                ->where(ProviderPrice::COLUMN_PROVIDER_ID, $row->getProviderId())
                ->where(ProviderPrice::COLUMN_VARIANT_ID, $row->getVariantId())
                ->first();

            if (($this->saveProviderPrice)(ProviderPriceData::fromArray([
                ProviderPrice::COLUMN_PROVIDER_ID => $row->getProviderId(),
                ProviderPrice::COLUMN_VARIANT_ID => $row->getVariantId(),
                ProviderPrice::COLUMN_COST => $row->getCost(),
                ProviderPrice::COLUMN_IS_AVAILABLE => $row->getIsAvailable(),
                ProviderPrice::COLUMN_IS_ENABLED => $row->getIsEnabled(),
                ProviderPrice::COLUMN_LEAD_TIME_HOURS => $row->getLeadTimeHours(),
                ProviderPrice::COLUMN_COMMISSION_PERCENT => $row->getCommissionPercent(),
                ProviderPrice::COLUMN_IS_PREFERRED => $row->getIsPreferred(),
            ]), $existing, $import->getAdminId(), PriceChangeReason::Import)->exists) {
                $updated++;
            }
        }

        $import->setRowsUpdated($updated)->setStatus(PriceImportStatus::Done)->setAppliedAt(now())->save();
        Storage::disk('local')->delete($import->getFilePath());
    }
}
