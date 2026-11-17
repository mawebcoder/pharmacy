<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Catalog\Actions\AddProductsToCollection;
use App\Modules\Catalog\Actions\RemoveProductFromCollection;
use App\Modules\Catalog\Actions\SaveCollection;
use App\Modules\Catalog\Data\CollectionData;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductCollection;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Queries\VariantPricingInputs;
use App\Modules\Catalog\Search\ProductSearchIndex;
use App\Modules\Content\Actions\SaveHomeWidget;
use App\Modules\Content\Data\HomeWidgetData;
use App\Modules\Content\Enums\HomeWidgetType;
use App\Modules\Content\Models\HomeWidget;
use App\Modules\Pricing\Actions\SaveProvider;
use App\Modules\Pricing\Actions\SaveProviderPrice;
use App\Modules\Pricing\Data\ProviderData;
use App\Modules\Pricing\Data\ProviderPriceData;
use App\Modules\Pricing\Enums\PriceChangeReason;
use App\Modules\Pricing\Jobs\RecalculateVariantPrices;
use App\Modules\Pricing\Models\ProviderPrice;
use App\Modules\Pricing\Queries\SupplierOptions;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Sample «زودمصرف» products so the badge, its «انقضای نزدیک؛ دلیل تخفیف» strip and the expiry date on the product page
 * can be seen (owner, 2026-11-17: near expiry is per supplier). For up to 6 products for sale outside a flash deal,
 * the demo supplier «تأمین‌کننده نمونه (زودمصرف)» becomes the default SKU's preferred supplier, with near-expiry stock
 * expiring in two months and a cost that keeps the selling price about the same; the products get a 10% haraj, go into
 * the collection «زودمصرف», shown on the home page as a collection row. Local and testing only; running it again keeps
 * one supplier, one collection and one row.
 *
 *   php artisan db:seed --class=NearExpiryDemoSeeder
 */
final class NearExpiryDemoSeeder extends Seeder
{
    public const string COLLECTION_SLUG = 'near-expiry';

    public const int PRODUCTS = 6;

    public const float HARAJ_PERCENT = 10;

    public const string SUPPLIER_NAME = 'تأمین‌کننده نمونه (زودمصرف)';

    public function run(SaveCollection $saveCollection, AddProductsToCollection $addProducts, RemoveProductFromCollection $removeProduct, SaveHomeWidget $saveWidget, SaveProvider $saveProvider, SaveProviderPrice $savePrice, SupplierOptions $suppliers, VariantPricingInputs $pricingInputs): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('NearExpiryDemoSeeder changes products and does not run in production.');
        }

        $products = Product::query()
            ->active()
            ->with(['variants'])
            ->orderByDesc('id')
            ->get()
            ->filter(fn (Product $product): bool => $product->isSellableType()
                && ($product->getDefaultVariant()?->isInStock() ?? false)
                && $product->getVariants()->every(fn (ProductVariant $variant): bool => $variant->getFlashDealId() === null))
            ->take(self::PRODUCTS)
            ->values();

        if ($products->isEmpty()) {
            $this->command?->warn('No product is for sale outside a flash deal; run HomePageDemoSeeder first. Nothing added.');

            return;
        }

        $supplierId = array_search(self::SUPPLIER_NAME, $suppliers->names(), true);
        $supplierId = $supplierId !== false
            ? (int) $supplierId
            : (int) $saveProvider(new ProviderData(self::SUPPLIER_NAME, null, null, null, null, null, true, 'نمونه برای نمایش زودمصرف.'))->getKey();

        foreach ($products as $product) {
            $product->setHarajPercent(self::HARAJ_PERCENT)->save();
            $variant = $product->getDefaultVariant();

            if (! $variant instanceof ProductVariant) {
                continue;
            }

            // A supplier sells only with a commission; demo products without one get 10%.
            if ($pricingInputs->commissionOf($product) === null) {
                $product->setCommissionPercent(10)->save();
            }

            // A cost whose selling price (with the product's commission) stays about today's price.
            $commission = $pricingInputs->commissionOf($product) ?? 10.0;
            $cost = (int) (floor($variant->getPrice() / (1 + $commission / 100) / 10_000) * 10_000);
            $existing = ProviderPrice::query()->where(ProviderPrice::COLUMN_PROVIDER_ID, $supplierId)->where(ProviderPrice::COLUMN_VARIANT_ID, $variant->getId())->first();

            $_ = $savePrice(new ProviderPriceData(
                providerId: $supplierId,
                variantId: $variant->getId(),
                cost: max(10_000, $cost),
                isAvailable: true,
                isEnabled: true,
                leadTimeHours: 24,
                isPreferred: true,
                isNearExpiry: true,
                expiryDate: CarbonImmutable::now()->addMonths(2)->startOfDay(),
            ), $existing);
        }

        // Prices, haraj and «زودمصرف» are worked out by the pricing engine; a seeder is not a request, so it runs it now.
        $variantIds = $products->flatMap(fn (Product $product): array => $product->getVariants()->map(fn (ProductVariant $variant): int => $variant->getId())->all())->values()->all();
        RecalculateVariantPrices::dispatchSync(array_values($variantIds), PriceChangeReason::HarajChanged);

        $collection = ProductCollection::query()->where(ProductCollection::COLUMN_SLUG, self::COLLECTION_SLUG)->first()
            ?? $saveCollection(new CollectionData('زودمصرف', 'Near expiry', self::COLLECTION_SLUG, 'کالاهایی که تاریخ انقضای نزدیک دارند و با تخفیف عرضه می‌شوند.', true, null, null, 0));
        $ids = array_values($products->map(fn (Product $product): int => $product->getId())->all());
        // The collection holds exactly these products (an earlier run may have put others in).
        foreach ($collection->products()->whereKeyNot($ids)->get() as $stale) {
            $removeProduct($collection, $stale);
        }

        $addProducts($collection, $ids);

        $hasRow = HomeWidget::query()
            ->where(HomeWidget::COLUMN_TYPE, HomeWidgetType::Collection)
            ->get()
            ->contains(fn (HomeWidget $widget): bool => $widget->getCollectionId() === $collection->getId());

        if (! $hasRow) {
            $_ = $saveWidget(HomeWidgetData::fromArray([HomeWidget::COLUMN_TYPE => HomeWidgetType::Collection, 'collection_id' => $collection->getId(), HomeWidget::COLUMN_TITLE_FA => 'زودمصرف']));
        }

        foreach ($products as $product) {
            ProductSearchIndex::refresh($product->fresh() ?? $product);
        }

        $this->command?->info($products->count().' products sell from near-expiry supplier stock with a 10% haraj, in the «زودمصرف» collection on the home page.');
    }
}
