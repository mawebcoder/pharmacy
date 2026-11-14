<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Database\Factories;

use App\Modules\Pricing\Enums\PriceImportStatus;
use App\Modules\Pricing\Models\PriceImport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceImport>
 */
final class PriceImportFactory extends Factory
{
    protected $model = PriceImport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            PriceImport::COLUMN_FILE_PATH => 'imports/prices/test.xlsx',
            PriceImport::COLUMN_ORIGINAL_NAME => 'prices.xlsx',
            PriceImport::COLUMN_STATUS => PriceImportStatus::Pending,
        ];
    }
}
