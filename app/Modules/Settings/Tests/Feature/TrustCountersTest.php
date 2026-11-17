<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Settings\Queries\TrustCounters;
use Illuminate\Support\Facades\Cache;

/*
| Trust counters (roadmap S35): products for sale, delivered orders and cities delivered to, each shown once it is
| big enough (100 orders, 10 cities; owner 2026-11-17), cached an hour.
*/

function deliveredOrders(int $count, int $cities): void
{
    foreach (range(1, $count) as $index) {
        Order::factory()->create([
            Order::COLUMN_STATUS => OrderStatus::Delivered,
            Order::COLUMN_ADDRESS_SNAPSHOT => ['city_id' => ($index % $cities) + 1, 'city' => 'شهر'],
        ]);
    }
}

it('counts products for sale and leaves out orders and cities while they are few', function (): void {
    foreach (range(1, 3) as $index) {
        ProductVariant::factory()->for(Product::factory()->active())->create();
    }
    deliveredOrders(5, 2);

    expect(app(TrustCounters::class)->shown())->toBe([['icon' => 'cube', 'value' => 3, 'label' => 'کالای موجود']]);
});

it('shows delivered orders and cities once there are enough, counting only delivered orders', function (): void {
    deliveredOrders(TrustCounters::MIN_ORDERS, TrustCounters::MIN_CITIES);
    Order::factory()->create([Order::COLUMN_STATUS => OrderStatus::Cancelled, Order::COLUMN_ADDRESS_SNAPSHOT => ['city_id' => 99]]);

    expect(array_column(app(TrustCounters::class)->shown(), 'value', 'label'))
        ->toBe(['سفارش تحویل‌شده' => TrustCounters::MIN_ORDERS, 'شهر زیر پوشش' => TrustCounters::MIN_CITIES]);
});

it('keeps the counts for an hour', function (): void {
    ProductVariant::factory()->for(Product::factory()->active())->create();
    app(TrustCounters::class)->shown();
    ProductVariant::factory()->for(Product::factory()->active())->create();

    expect(app(TrustCounters::class)->shown()[0]['value'])->toBe(1);

    $this->travel(61)->minutes();

    expect(app(TrustCounters::class)->shown()[0]['value'])->toBe(2);
});

it('leaves the products for sale out of the footer', function (): void {
    Cache::forget(TrustCounters::CACHE_KEY);
    ProductVariant::factory()->for(Product::factory()->active())->create();
    deliveredOrders(TrustCounters::MIN_ORDERS, TrustCounters::MIN_CITIES);

    $this->get(route('faq'))
        ->assertOk()
        ->assertSeeInOrder(['ضمانت بازگشت', 'سفارش تحویل‌شده', 'شهر زیر پوشش'])
        ->assertDontSee('کالای موجود');
});
