<?php

declare(strict_types=1);

namespace App\Modules\Locations\Console;

use App\Modules\Locations\Actions\ImportTipaxCities;
use App\Modules\Locations\Data\TipaxCityData;
use App\Modules\Locations\Exceptions\InvalidTipaxCities;
use App\Modules\Shared\Support\PersianDigits;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Imports provinces and cities from Tipax's city list (roadmap D4.3, decided 2026-11-14 in S18). Reads the JSON
 * response of `Cities/plusstate` (and optionally `Cities/BranchCities`) saved to files; S22's Tipax client calls the
 * API and feeds the same action.
 */
final class ImportTipaxCitiesCommand extends Command
{
    protected $signature = 'locations:import-tipax
        {file : JSON of Tipax GET /api/OM/v4/Cities/plusstate (a list of cities)}
        {--branches= : JSON of Tipax GET /api/OM/v4/Cities/BranchCities, to mark cities with a Tipax branch}';

    protected $description = 'Import provinces and cities from Tipax\'s city list';

    public function handle(ImportTipaxCities $import): int
    {
        try {
            $cities = [];

            foreach ($this->rows((string) $this->argument('file')) as $index => $row) {
                $cities[] = TipaxCityData::fromArray($row, $index + 1);
            }

            $branches = filled($this->option('branches')) ? TipaxCityData::idsOf($this->rows((string) $this->option('branches'))) : null;
        } catch (InvalidTipaxCities $invalid) {
            $this->components->error($invalid->getMessage());

            return self::FAILURE;
        }

        $result = $import($cities, $branches);
        $digits = PersianDigits::toPersian(...);

        $this->components->info("استان‌ها: {$digits($result->provinces)} · شهرها: {$digits($result->cities)} (جدید: {$digits($result->newCities)}) · شهرهای قبلی که در تیپاکس نیستند: {$digits($result->notInTipax)}");

        return self::SUCCESS;
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws InvalidTipaxCities
     */
    private function rows(string $path): array
    {
        if (! File::isFile($path)) {
            throw InvalidTipaxCities::missingFile($path);
        }

        $decoded = json_decode((string) File::get($path), true);
        $list = is_array($decoded) && array_is_list($decoded) ? $decoded : ($decoded['data'] ?? $decoded['cities'] ?? null);

        if (! is_array($list) || ! array_is_list($list)) {
            throw InvalidTipaxCities::invalidJson($path);
        }

        return array_values(array_filter($list, is_array(...)));
    }
}
