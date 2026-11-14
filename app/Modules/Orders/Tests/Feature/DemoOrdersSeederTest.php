<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Orders\Database\Seeders\DemoOrdersSeeder;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderStatusLog;

it('seeds one demo order per status, with items and status logs, and replaces them when run again', function (): void {
    ProductVariant::factory()->count(2)->for(Product::factory()->active())->create();

    $this->seed(DemoOrdersSeeder::class);
    $this->seed(DemoOrdersSeeder::class);

    $orders = Order::query()->with('items')->where(Order::COLUMN_ADMIN_NOTE, DemoOrdersSeeder::DEMO_NOTE)->get();
    $delivered = $orders->first(fn (Order $order): bool => $order->getStatus() === OrderStatus::Delivered);

    expect($orders)->toHaveCount(count(OrderStatus::cases()))
        ->and($orders->map(fn (Order $order): string => $order->getStatus()->value)->sort()->values()->all())
        ->toBe(collect(OrderStatus::cases())->map->value->sort()->values()->all())
        ->and($orders->every(fn (Order $order): bool => $order->items->isNotEmpty() && $order->getNumber() !== null))->toBeTrue()
        ->and(OrderStatusLog::query()->where(OrderStatusLog::COLUMN_ORDER_ID, $delivered?->getId())->count())->toBe(5);
});

it('seeds nothing in production', function (): void {
    ProductVariant::factory()->for(Product::factory()->active())->create();
    app()->detectEnvironment(fn (): string => 'production');

    $this->seed(DemoOrdersSeeder::class);

    expect(Order::query()->count())->toBe(0);
});
