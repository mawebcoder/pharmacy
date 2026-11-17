<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\ProductCollection;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Content\Enums\HomeWidgetType;
use App\Modules\Content\Models\HomeWidget;
use App\Modules\Pricing\Jobs\RecalculateVariantPrices;
use Database\Seeders\HomePageDemoSeeder;
use Database\Seeders\NearExpiryDemoSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Storage::fake('media');
    // The seeder runs the pricing engine itself; everything else stays queued.
    Queue::fake()->except([RecalculateVariantPrices::class]);
    $this->seed(HomePageDemoSeeder::class);
});

it('shows a «زودمصرف» collection row on the home page, with the badge and why it is discounted', function (): void {
    $this->seed(NearExpiryDemoSeeder::class);
    $this->seed(NearExpiryDemoSeeder::class);

    $collection = ProductCollection::query()->where(ProductCollection::COLUMN_SLUG, NearExpiryDemoSeeder::COLLECTION_SLUG)->sole();
    $rows = HomeWidget::query()->where(HomeWidget::COLUMN_TYPE, HomeWidgetType::Collection)->get()
        ->filter(fn (HomeWidget $widget): bool => $widget->getCollectionId() === $collection->getId());

    $near = ProductVariant::query()->where(ProductVariant::COLUMN_IS_NEAR_EXPIRY, true)->get();

    expect($near)->toHaveCount(NearExpiryDemoSeeder::PRODUCTS)
        ->and($near->every(fn (ProductVariant $variant): bool => $variant->getExpiryDate() !== null && $variant->getSalePrice() !== null))->toBeTrue()
        ->and($collection->products()->count())->toBe(NearExpiryDemoSeeder::PRODUCTS)
        ->and($rows)->toHaveCount(1);

    $this->get(route('home'))->assertOk()->assertSee(['زودمصرف', 'انقضای نزدیک؛ دلیل تخفیف']);
});
