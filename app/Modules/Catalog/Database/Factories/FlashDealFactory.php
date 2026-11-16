<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\FlashDeal;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Live flash deals (started an hour ago, ending in a day) at 800,000 rial, without a unit limit. Run
 * SyncFlashDealPrices to put the price on the SKU. States: limitedTo(), upcoming(), ended().
 *
 * @extends Factory<FlashDeal>
 */
final class FlashDealFactory extends Factory
{
    protected $model = FlashDeal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            FlashDeal::COLUMN_VARIANT_ID => ProductVariant::factory(),
            FlashDeal::COLUMN_DEAL_PRICE => 800_000,
            FlashDeal::COLUMN_STARTS_AT => now()->subHour(),
            FlashDeal::COLUMN_ENDS_AT => now()->addDay(),
            FlashDeal::COLUMN_QUANTITY_LIMIT => null,
            FlashDeal::COLUMN_SOLD_COUNT => 0,
            FlashDeal::COLUMN_IS_ACTIVE => true,
        ];
    }

    public function limitedTo(int $units): static
    {
        return $this->state(fn (array $attributes): array => [FlashDeal::COLUMN_QUANTITY_LIMIT => $units]);
    }

    public function upcoming(): static
    {
        return $this->state(fn (array $attributes): array => [FlashDeal::COLUMN_STARTS_AT => now()->addHour(), FlashDeal::COLUMN_ENDS_AT => now()->addDay()]);
    }

    public function ended(): static
    {
        return $this->state(fn (array $attributes): array => [FlashDeal::COLUMN_STARTS_AT => now()->subDays(2), FlashDeal::COLUMN_ENDS_AT => now()->subDay()]);
    }
}
