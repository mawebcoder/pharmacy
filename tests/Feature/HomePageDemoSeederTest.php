<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Product;
use App\Modules\Content\Models\HomeWidget;
use App\Modules\Content\Queries\HomeWidgetItems;
use Database\Seeders\HomePageDemoSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Storage::fake('media');
    // Image conversions are not needed to count what the widgets show.
    Queue::fake();
});

it('gives every widget type at least 6 items on the home page', function (): void {
    $this->seed(HomePageDemoSeeder::class);

    $shown = app(HomeWidgetItems::class)->shown();

    expect(array_map(fn (array $entry): string => $entry['widget']->getType()->value, $shown))
        ->toBe(['slider', 'categories', 'collection', 'brands', 'collection', 'tags', 'trust'])
        ->and(array_map(fn (array $entry): ?int => $entry['items']?->count(), $shown))
        ->toBe([6, 6, 6, 6, 6, 6, null]);

    $this->get(route('home'))->assertOk()->assertSee('پیشنهاد ویژه');
});

it('adds nothing when the sample data is already there', function (): void {
    $this->seed(HomePageDemoSeeder::class);

    $this->seed(HomePageDemoSeeder::class);

    expect(Product::query()->count())->toBe(12)
        ->and(HomeWidget::query()->count())->toBe(7);
});
