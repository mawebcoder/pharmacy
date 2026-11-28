<?php

declare(strict_types=1);

use App\Modules\Catalog\Events\ProductsSearchFieldsChanged;
use App\Modules\Catalog\Jobs\RecalculateProductSales;
use App\Modules\Catalog\Models\Product;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Queries\ProductSales;
use Illuminate\Support\Facades\Event;

function soldItem(Order $order, Product $product, int $quantity): void
{
    (new OrderItem)
        ->setOrderId($order->getId())
        ->setProductId($product->getId())
        ->setProductName($product->getNameFa())
        ->setSku('SKU-'.fake()->unique()->numerify('#####'))
        ->setQuantity($quantity)
        ->setProductSnapshot([])
        ->setListPrice(100_000)
        ->setUnitPrice(100_000)
        ->setPaidUnit(100_000)
        ->setPaidTotal(100_000 * $quantity)
        ->save();
}

it('counts each product\'s units in paid orders of the last 30 days, and re-indexes only the products that changed', function (): void {
    Event::fake([ProductsSearchFieldsChanged::class]);
    $popular = Product::factory()->create();
    $quiet = Product::factory()->create();
    $fading = Product::factory()->create()->setSales30d(5);
    $fading->save();
    $steady = Product::factory()->create()->setSales30d(1);
    $steady->save();

    soldItem(Order::factory()->paid()->create(), $popular, 2);
    soldItem(Order::factory()->paid()->create([Order::COLUMN_STATUS => OrderStatus::Delivered]), $popular, 3);
    soldItem(Order::factory()->paid()->create(), $steady, 1);
    soldItem(Order::factory()->paid()->create([Order::COLUMN_STATUS => OrderStatus::Cancelled]), $quiet, 4);
    soldItem(Order::factory()->create(), $quiet, 4);
    soldItem(Order::factory()->paid()->create([Order::COLUMN_PAID_AT => now()->subDays(31)]), $quiet, 4);

    (new RecalculateProductSales)->handle(app(ProductSales::class));

    expect($popular->fresh()->getSales30d())->toBe(5)
        ->and($quiet->fresh()->getSales30d())->toBe(0)
        ->and($fading->fresh()->getSales30d())->toBe(0)
        ->and($steady->fresh()->getSales30d())->toBe(1);
    Event::assertDispatched(ProductsSearchFieldsChanged::class, fn (ProductsSearchFieldsChanged $event): bool => collect($event->productIds)->sort()->values()->all() === collect([$popular->getId(), $fading->getId()])->sort()->values()->all());
});
