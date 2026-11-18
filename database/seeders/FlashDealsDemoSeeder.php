<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Catalog\Actions\SaveFlashDeal;
use App\Modules\Catalog\Actions\SyncFlashDealPrices;
use App\Modules\Catalog\Data\FlashDealData;
use App\Modules\Catalog\Models\FlashDeal;
use App\Modules\Catalog\Models\Product;
use App\Modules\Content\Actions\SetFlashDealWidget;
use App\Modules\Pricing\Actions\SaveProvider;
use App\Modules\Pricing\Actions\SaveProviderPrice;
use App\Modules\Pricing\Data\ProviderData;
use App\Modules\Pricing\Data\ProviderPriceData;
use App\Modules\Pricing\Queries\SupplierOptions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Sample flash deals (roadmap S28) so the home page «پیشنهاد لحظه‌ای» row and /deals have something to show: up to 6
 * products for sale get a deal at about 20% off their regular price, each with its own end (2 to 12 hours from now,
 * so the countdown runs to the last one), 20 units each, from the demo supplier «تأمین‌کننده نمونه (پیشنهاد لحظه‌ای)».
 * That supplier is added to the SKUs with «فروش از این تأمین‌کننده» off, so regular prices do not change. The home
 * row is turned on. Local and testing only; running it again replaces the demo deals that sold nothing.
 *
 *   php artisan db:seed --class=FlashDealsDemoSeeder
 */
final class FlashDealsDemoSeeder extends Seeder
{
    public const string SUPPLIER_NAME = 'تأمین‌کننده نمونه (پیشنهاد لحظه‌ای)';

    public const int DEALS = 6;

    public const int UNITS = 20;

    public function run(SaveProvider $saveProvider, SaveProviderPrice $savePrice, SaveFlashDeal $saveDeal, SetFlashDealWidget $setWidget, SupplierOptions $suppliers, SyncFlashDealPrices $syncPrices): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('FlashDealsDemoSeeder adds sample deals and does not run in production.');
        }

        $products = Product::query()
            ->active()
            ->with(['variants'])
            ->get()
            ->filter(fn (Product $product): bool => $product->isSellableType() && ($product->getDefaultVariant()?->isInStock() ?? false))
            ->take(self::DEALS)
            ->values();

        if ($products->isEmpty()) {
            $this->command?->warn('No product is for sale; run HomePageDemoSeeder first. Nothing added.');

            return;
        }

        $supplierId = array_search(self::SUPPLIER_NAME, $suppliers->names(), true);
        $supplierId = $supplierId !== false
            ? (int) $supplierId
            : (int) $saveProvider(new ProviderData(self::SUPPLIER_NAME, null, null, null, null, null, true, 'نمونه برای پیشنهاد لحظه‌ای؛ سایت از آن نمی‌فروشد.'))->getKey();

        foreach ($products as $index => $product) {
            $variant = $product->getDefaultVariant();

            if ($variant === null) {
                continue;
            }

            $regular = $variant->regularPrice();
            // About 20% off, in whole thousands of toman; the supplier's cost a little below it.
            $dealPrice = max(10_000, intdiv($regular * 80, 100 * 10_000) * 10_000);

            if ($dealPrice >= $regular) {
                continue;
            }

            if ($suppliers->cost($supplierId, $variant->getId()) === null) {
                $_ = $savePrice(new ProviderPriceData($supplierId, $variant->getId(), intdiv($dealPrice * 85, 100), isAvailable: true, isEnabled: false, leadTimeHours: 24));
            }

            FlashDeal::query()
                ->where(FlashDeal::COLUMN_VARIANT_ID, $variant->getId())
                ->where(FlashDeal::COLUMN_SOLD_COUNT, 0)
                ->delete();

            $saveDeal(new FlashDealData(
                variantId: $variant->getId(),
                dealPrice: $dealPrice,
                startsAt: Carbon::now()->subMinutes(5),
                endsAt: Carbon::now()->addHours(2 + 2 * $index),
                quantityLimit: self::UNITS,
                isActive: true,
                providerId: $supplierId,
            ));
        }

        // Deal prices are synced by a queued job; a seeder is not a request, so it puts them on the SKUs now.
        $syncPrices();
        $setWidget(true);

        $this->command?->info('Flash deals added for '.$products->count().' products; the home page «پیشنهاد لحظه‌ای» row is on.');
    }
}
