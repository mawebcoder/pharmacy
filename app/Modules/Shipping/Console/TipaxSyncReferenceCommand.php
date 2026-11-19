<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Console;

use App\Modules\Shared\Support\PersianDigits;
use App\Modules\Shipping\Actions\SyncTipaxReferences;
use App\Modules\Shipping\Enums\TipaxReferenceType;
use App\Modules\Shipping\Exceptions\TipaxRequestFailed;
use App\Modules\Shipping\Tipax\TipaxClient;
use Illuminate\Console\Command;

/**
 * Loads Tipax's services, pack types, packings and package contents (roadmap D11.3 step 7, S33) for the admin's
 * dropdowns in the store settings. All four are read first; any failure changes nothing and exits non-zero.
 */
final class TipaxSyncReferenceCommand extends Command
{
    protected $signature = 'tipax:sync-reference';

    protected $description = 'Load Tipax services, pack types, packings and package contents';

    public function handle(TipaxClient $tipax, SyncTipaxReferences $sync): int
    {
        try {
            $lists = [
                TipaxReferenceType::Service->value => $tipax->services(),
                TipaxReferenceType::PackType->value => $tipax->packTypes(),
                TipaxReferenceType::Packing->value => $tipax->packingTypes(),
                TipaxReferenceType::Content->value => $tipax->packagingContents(),
            ];
        } catch (TipaxRequestFailed $failed) {
            $this->components->error($failed->getMessage());

            return self::FAILURE;
        }

        $saved = $sync($lists);

        $this->components->info(implode(' · ', array_map(
            fn (TipaxReferenceType $type): string => $type->label().': '.PersianDigits::toPersian((string) ($saved[$type->value] ?? 0)),
            TipaxReferenceType::cases(),
        )));

        return self::SUCCESS;
    }
}
