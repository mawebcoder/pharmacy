<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Listeners;

use App\Modules\Catalog\Filament\Imports\ProductImporter;
use App\Modules\Shared\Support\PersianDigits;
use Filament\Actions\Imports\Events\ImportChunkProcessed;

/**
 * Filament's ImportChunkProcessed, after each chunk of 100 rows of a catalog import (owner, 2026-11-27): when rows of
 * the chunk failed, the admin who imported is told at once under the panel's bell (notify_admin). Filament's own
 * notification at the end gives the failed-rows CSV with each row's reason.
 */
final class NotifyAdminOfImportChunk
{
    public function handle(ImportChunkProcessed $event): void
    {
        $import = $event->getImport();
        $failed = $event->getProcessedRows() - $event->getSuccessfulRows();

        $adminId = $import->getAttribute('user_id');

        if ($import->importer !== ProductImporter::class || $failed < 1 || $adminId === null) {
            return;
        }

        notify_admin(
            (int) $adminId,
            'ورود کالا: '.PersianDigits::toPersian((string) $failed).' ردیف وارد نشد',
            'در فایل «'.$import->file_name.'» '.PersianDigits::toPersian((string) $failed).' ردیف از '.PersianDigits::toPersian((string) $event->getProcessedRows()).' ردیف این بخش ذخیره نشد. پایان ورود، فایل ردیف‌های ناموفق با دلیل هر کدام فرستاده می‌شود.',
            'warning',
        );
    }
}
