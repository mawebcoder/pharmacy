<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Actions;

use App\Modules\Pricing\Enums\PriceImportStatus;
use App\Modules\Pricing\Jobs\ParsePriceImport;
use App\Modules\Pricing\Models\PriceImport;

/**
 * Starts a supplier price import (roadmap D13.9, S16) for an uploaded file on the local disk: the rows are read and
 * checked in the background on the «price-import» queue; nothing changes until the admin applies the preview.
 */
final class StartPriceImport
{
    #[\NoDiscard]
    public function __invoke(string $filePath, string $originalName, ?int $adminId = null): PriceImport
    {
        $import = (new PriceImport)
            ->setFilePath($filePath)
            ->setOriginalName($originalName)
            ->setStatus(PriceImportStatus::Pending)
            ->setAdminId($adminId);
        $import->save();

        ParsePriceImport::dispatch($import->getId())->afterCommit();

        return $import;
    }
}
