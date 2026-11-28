<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Product;
use App\Modules\Content\Models\HomeWidget;
use App\Modules\Content\Queries\HomeWidgetItems;
use Database\Seeders\HomePageDemoSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

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

    // Widgets below the first screen load as the visitor scrolls (content::home-widget).
    $html = collect($shown)
        ->map(fn (array $entry): string => livewire('content::home-widget', ['widgetId' => $entry['widget']->getId(), 'type' => $entry['widget']->getType()->value])->call('load')->html())
        ->implode('');

    $this->get(route('home'))->assertOk();
    expect($html)->toContain('پیشنهاد ویژه');
});

it('adds nothing when the sample data is already there', function (): void {
    $this->seed(HomePageDemoSeeder::class);

    $this->seed(HomePageDemoSeeder::class);

    expect(Product::query()->count())->toBe(12)
        ->and(HomeWidget::query()->count())->toBe(7);
});
