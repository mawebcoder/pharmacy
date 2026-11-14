<?php

declare(strict_types=1);

use App\Modules\Catalog\Actions\ReplacePopularProducts;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductCollection;
use App\Modules\Search\Jobs\RecalculatePopularProducts;
use App\Modules\Search\Models\SearchLog;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;

function openedFromSearch(Product $product, int $times, string $at = '2026-11-14 10:00'): void
{
    foreach (range(1, $times) as $time) {
        (new SearchLog)
            ->setQuery('کرم')
            ->setNormalizedQuery('کرم')
            ->setResultsCount(3)
            ->setClickedProductId($product->getId())
            ->forceFill([SearchLog::COLUMN_CREATED_AT => Carbon::parse($at)])
            ->save();
    }
}

function popularProductIds(): array
{
    return ProductCollection::query()->where(ProductCollection::COLUMN_SLUG, ProductCollection::POPULAR_SLUG)->sole()
        ->products()->orderBy('collection_product.'.ProductCollection::PIVOT_SORT)->pluck('products.id')->map(intval(...))->all();
}

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-11-15 03:30'));
});

it('ranks products by how often they were opened from search in the last 30 days, active ones only', function (): void {
    [$most, $recent, $older] = Product::factory()->count(3)->active()->create()->all();
    $draft = Product::factory()->create();
    openedFromSearch($most, 3);
    openedFromSearch($older, 1, '2026-11-10 10:00');
    openedFromSearch($recent, 1, '2026-11-14 12:00');
    openedFromSearch($draft, 5);
    openedFromSearch($older, 10, '2026-09-01 10:00');

    (new RecalculatePopularProducts)->handle(app(ReplacePopularProducts::class));

    expect(popularProductIds())->toBe([$most->getId(), $recent->getId(), $older->getId()]);
});

it('keeps the current list on a night without search clicks', function (): void {
    $kept = Product::factory()->active()->create();
    ProductCollection::query()->where(ProductCollection::COLUMN_SLUG, ProductCollection::POPULAR_SLUG)->sole()
        ->products()->attach($kept->getId(), [ProductCollection::PIVOT_SORT => 1]);

    (new RecalculatePopularProducts)->handle(app(ReplacePopularProducts::class));

    expect(popularProductIds())->toBe([$kept->getId()]);
});

it('is scheduled every night', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains((string) $event->description, RecalculatePopularProducts::class));

    expect($event?->expression)->toBe('30 3 * * *')
        ->and($event?->timezone)->toBe(config('app.display_timezone'));
});
