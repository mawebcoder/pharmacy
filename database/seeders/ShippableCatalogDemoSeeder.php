<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Catalog\Enums\StockStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Pricing\Actions\SaveProviderPrice;
use App\Modules\Pricing\Data\ProviderPriceData;
use App\Modules\Pricing\Enums\PriceChangeReason;
use App\Modules\Pricing\Jobs\RecalculateVariantPrices;
use App\Modules\Pricing\Models\Provider;
use App\Modules\Pricing\Models\ProviderPrice;
use App\Modules\Shipping\Actions\SaveVariantBoxCapacities;
use App\Modules\Shipping\Data\BoxSpec;
use App\Modules\Shipping\Database\Seeders\TipaxCartonsSeeder;
use App\Modules\Shipping\Models\VariantBoxCapacity;
use App\Modules\Shipping\Queries\ActiveBoxes;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Makes every SKU checkout-ready, so shipping can be priced from our packing (roadmap S31, S34): a weight, how many
 * pieces fit each active carton, in stock, and a price record from a sample supplier. Only what is missing is filled;
 * a SKU the admin already set up keeps its values. Without products it first adds the home page samples. Local and
 * testing only.
 *
 *   php artisan db:seed --class=ShippableCatalogDemoSeeder
 */
final class ShippableCatalogDemoSeeder extends Seeder
{
    public const string PROVIDER_NAME = 'تأمین‌کننده نمونه';

    /**
     * Sample weights (g) handed out in turn to SKUs without one: a serum, a cream, a shampoo, a supplement jar, a
     * large lotion.
     */
    private const array WEIGHTS_G = [120, 250, 400, 650, 900];

    /**
     * Space one piece takes (cm³) by weight: up to 200 g, up to 500 g, heavier. Only 80% of a carton is usable.
     */
    private const array PIECE_VOLUMES_CM3 = [200 => 250, 500 => 700, PHP_INT_MAX => 1_600];

    private const float USABLE_SHARE = 0.8;

    public function run(ActiveBoxes $activeBoxes, SaveVariantBoxCapacities $saveCapacities, SaveProviderPrice $saveProviderPrice): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('ShippableCatalogDemoSeeder adds sample data and does not run in production.');
        }

        $this->call(TipaxCartonsSeeder::class);

        if (! Product::query()->exists()) {
            $this->call(HomePageDemoSeeder::class);
        }

        $boxes = $activeBoxes->specs();
        $provider = Provider::query()->where(Provider::COLUMN_NAME, self::PROVIDER_NAME)->first()
            ?? Provider::factory()->create([Provider::COLUMN_NAME => self::PROVIDER_NAME]);
        $withCapacities = VariantBoxCapacity::query()->distinct()->pluck(VariantBoxCapacity::COLUMN_VARIANT_ID)->flip();
        $withSupplier = ProviderPrice::query()->distinct()->pluck(ProviderPrice::COLUMN_VARIANT_ID)->flip();
        $filled = ['weight' => 0, 'boxes' => 0, 'supplier' => 0];
        $variantIds = [];

        foreach (ProductVariant::query()->orderBy(ProductVariant::COLUMN_ID)->get() as $index => $variant) {
            $variantIds[] = $variant->getId();

            if ($variant->getWeightG() === null) {
                $variant->setWeightG(self::WEIGHTS_G[$index % count(self::WEIGHTS_G)]);
                $filled['weight']++;
            }

            $variant->setStockStatus(StockStatus::Available)->save();
            $weight = (int) $variant->getWeightG();

            if (! isset($withCapacities[$variant->getId()])) {
                $saveCapacities($variant->getId(), $this->capacities($weight, $boxes));
                $filled['boxes']++;
            }

            if (! isset($withSupplier[$variant->getId()]) && $variant->getPrice() > 0) {
                $_ = $saveProviderPrice(new ProviderPriceData(
                    providerId: $provider->getId(),
                    variantId: $variant->getId(),
                    cost: (int) round($variant->getPrice() * 0.8),
                    isAvailable: true,
                    isEnabled: true,
                    leadTimeHours: 24,
                    isPreferred: true,
                ));
                $filled['supplier']++;
            }
        }

        // Price records now, not in the background, so checkout works as soon as seeding ends.
        RecalculateVariantPrices::dispatchSync($variantIds, PriceChangeReason::Recalculated);

        $this->command?->info(sprintf(
            'SKUs: %d · weight filled: %d · carton numbers filled: %d · supplier price added: %d',
            count($variantIds),
            $filled['weight'],
            $filled['boxes'],
            $filled['supplier'],
        ));
    }

    /**
     * How many pieces of this weight fit each carton by space; the packer applies each carton's weight limit.
     *
     * @param  list<BoxSpec>  $boxes
     * @return array<int, int|null> box id => pieces (null: does not fit)
     */
    private function capacities(int $weightG, array $boxes): array
    {
        $pieceVolume = max(self::PIECE_VOLUMES_CM3);

        foreach (self::PIECE_VOLUMES_CM3 as $upToG => $volume) {
            if ($weightG <= $upToG) {
                $pieceVolume = $volume;

                break;
            }
        }

        $capacities = [];

        foreach ($boxes as $box) {
            $pieces = (int) floor($box->volume() * self::USABLE_SHARE / $pieceVolume);
            $capacities[$box->id] = $pieces > 0 ? $pieces : null;
        }

        return $capacities;
    }
}
