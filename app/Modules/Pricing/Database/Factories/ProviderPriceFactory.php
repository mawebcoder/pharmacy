<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Database\Factories;

use App\Modules\Catalog\Database\Factories\ProductVariantFactory;
use App\Modules\Pricing\Models\Provider;
use App\Modules\Pricing\Models\ProviderPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An available, enabled price from an active supplier for a new SKU. States: unavailable(), disabled(), forVariant().
 *
 * @extends Factory<ProviderPrice>
 */
final class ProviderPriceFactory extends Factory
{
    protected $model = ProviderPrice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            ProviderPrice::COLUMN_PROVIDER_ID => Provider::factory(),
            ProviderPrice::COLUMN_VARIANT_ID => fn (): int => (int) ProductVariantFactory::new()->createOne()->getKey(),
            ProviderPrice::COLUMN_COST => fake()->numberBetween(20, 900) * 10_000,
            ProviderPrice::COLUMN_IS_AVAILABLE => true,
            ProviderPrice::COLUMN_IS_ENABLED => true,
            ProviderPrice::COLUMN_LEAD_TIME_HOURS => 24,
        ];
    }

    public function forVariant(int $variantId): static
    {
        return $this->state(fn (array $attributes): array => [ProviderPrice::COLUMN_VARIANT_ID => $variantId]);
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => [ProviderPrice::COLUMN_IS_ENABLED => false]);
    }

    public function unavailable(): static
    {
        return $this->state(fn (array $attributes): array => [ProviderPrice::COLUMN_IS_AVAILABLE => false]);
    }
}
