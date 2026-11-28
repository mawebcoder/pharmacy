<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\FlashDeal;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Content\Actions\SetFlashDealWidget;
use App\Modules\Content\Enums\HomeWidgetType;
use App\Modules\Content\Queries\HomeWidgetItems;
use Database\Seeders\FlashDealsDemoSeeder;
use Database\Seeders\HomePageDemoSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Storage::fake('local');
    Storage::fake('media');
    Queue::fake();
    $this->seed(HomePageDemoSeeder::class);
});

it('puts 6 live deals in the home page «پیشنهاد لحظه‌ای» row without changing regular prices', function (): void {
    $regular = ProductVariant::query()->get()->mapWithKeys(fn (ProductVariant $variant): array => [$variant->getId() => $variant->regularPrice()]);

    $this->seed(FlashDealsDemoSeeder::class);
    $this->seed(FlashDealsDemoSeeder::class);

    $deals = FlashDeal::query()->live()->with('variant')->get();
    $row = collect(app(HomeWidgetItems::class)->shown())->first(fn (array $entry): bool => $entry['widget']->getType() === HomeWidgetType::FlashDeal);

    expect($deals)->toHaveCount(FlashDealsDemoSeeder::DEALS)
        ->and(SetFlashDealWidget::isOn())->toBeTrue()
        ->and($row['items'] ?? null)->toHaveCount(FlashDealsDemoSeeder::DEALS)
        ->and($deals->every(fn (FlashDeal $deal): bool => $deal->getVariant()?->regularPrice() === $regular[$deal->getVariantId()]
            && $deal->getVariant()->currentPrice() === $deal->getDealPrice()))->toBeTrue();

    $this->get(route('home'))->assertOk();
    livewire('content::home-widget', ['widgetId' => $row['widget']->getId(), 'type' => HomeWidgetType::FlashDeal->value])
        ->call('load')
        ->assertSee('پیشنهاد لحظه‌ای');
});
