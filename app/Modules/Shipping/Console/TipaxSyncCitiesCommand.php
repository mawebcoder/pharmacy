<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Console;

use App\Modules\Locations\Actions\ImportTipaxCities;
use App\Modules\Locations\Data\TipaxCityData;
use App\Modules\Locations\Exceptions\InvalidTipaxCities;
use App\Modules\Shared\Support\PersianDigits;
use App\Modules\Shipping\Exceptions\TipaxRequestFailed;
use App\Modules\Shipping\Tipax\TipaxClient;
use Illuminate\Console\Command;

/**
 * Loads every Tipax province and city into our tables (roadmap D4.3, decided 2026-11-14 in S20): run on each deploy
 * after `migrate`, and weekly. Same import as `locations:import-tipax`, but straight from the Tipax API, and rows Tipax
 * lists without a valid province or id are skipped and counted instead of stopping the sync (2026-11-18). New cities
 * start open for delivery.
 */
final class TipaxSyncCitiesCommand extends Command
{
    protected $signature = 'tipax:sync-cities';

    protected $description = 'Load all provinces and cities from the Tipax API';

    public function handle(TipaxClient $tipax, ImportTipaxCities $import): int
    {
        try {
            $cities = [];
            $skipped = 0;

            foreach ($tipax->cities() as $index => $row) {
                try {
                    $cities[] = TipaxCityData::fromArray($row, $index + 1);
                } catch (InvalidTipaxCities) {
                    // Tipax lists placeholder rows (e.g. «RW city» with no province); they cannot be delivered to.
                    $skipped++;
                }
            }

            $branches = TipaxCityData::idsOf($tipax->branchCities());
        } catch (TipaxRequestFailed|InvalidTipaxCities $failed) {
            $this->components->error($failed->getMessage());

            return self::FAILURE;
        }

        if ($cities === []) {
            $this->components->error('Tipax returned no cities; nothing was changed.');

            return self::FAILURE;
        }

        $result = $import($cities, $branches);
        $digits = PersianDigits::toPersian(...);

        $this->components->info("استان‌ها: {$digits($result->provinces)} · شهرها: {$digits($result->cities)} (جدید: {$digits($result->newCities)}) · شهرهای قبلی که در تیپاکس نیستند: {$digits($result->notInTipax)} · ردیف‌های ناقص کنارگذاشته: {$digits($skipped)}");

        return self::SUCCESS;
    }
}
