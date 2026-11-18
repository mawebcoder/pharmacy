<?php

declare(strict_types=1);

use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Filament\Resources\Orders\Pages\ViewOrder;
use App\Modules\Orders\Listeners\MarkOrderPaid;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Payments\Events\PaymentSucceeded;
use App\Modules\Shipping\Database\Seeders\TipaxCartonsSeeder;
use App\Modules\Shipping\Listeners\PackPaidOrder;
use App\Modules\Shipping\Models\OrderParcel;
use App\Modules\Shipping\Models\ShippingBox;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Queue;

use function Pest\Livewire\livewire;

/*
| A paid order is packed into parcels and they are kept on it (roadmap S31, owner 2026-11-17).
*/

beforeEach(function (): void {
    $this->seed(TipaxCartonsSeeder::class);
});

function tipaxBox(int $size): ShippingBox
{
    return ShippingBox::query()->where(ShippingBox::COLUMN_TIPAX_SIZE, $size)->sole();
}

/**
 * How many fit Tipax cartons 1, 2 and 4, as a SKU form would have them.
 *
 * @return array<int, int>
 */
function fits(string $size): array
{
    return match ($size) {
        'small' => [tipaxBox(1)->getId() => 3, tipaxBox(2)->getId() => 7, tipaxBox(4)->getId() => 28],
        'medium' => [tipaxBox(1)->getId() => 1, tipaxBox(2)->getId() => 2, tipaxBox(4)->getId() => 10],
        'large' => [tipaxBox(2)->getId() => 1, tipaxBox(4)->getId() => 4],
    };
}

/**
 * @param  array<int, int>  $fits
 */
function packedOrderItem(Order $order, string $name, int $quantity, int $weight, array $fits, bool $liquid = false): OrderItem
{
    $item = (new OrderItem)
        ->setOrderId($order->getId())
        ->setProductName($name)
        ->setSku('SKU-'.fake()->unique()->numerify('#####'))
        ->setQuantity($quantity)
        ->setProductSnapshot(['sku' => ['weight_g' => $weight, 'box_capacities' => $fits, 'is_liquid' => $liquid, 'is_fragile' => false, 'ship_separately' => false]])
        ->setListPrice(500_000)
        ->setUnitPrice(500_000)
        ->setPaidUnit(500_000)
        ->setPaidTotal(500_000 * $quantity);
    $item->save();

    return $item;
}

it('packs a paid order in the background, never while the customer waits', function (): void {
    Queue::fake();
    $order = Order::factory()->create();
    packedOrderItem($order, 'کرم', 1, 120, fits('small'));

    app(MarkOrderPaid::class)->handle(new PaymentSucceeded($order->getId(), 1));

    Queue::assertPushed(CallQueuedListener::class, fn (CallQueuedListener $job): bool => $job->class === PackPaidOrder::class);
    expect(OrderParcel::query()->count())->toBe(0);
});

it('packs an order into the smallest standard Tipax carton once it is paid', function (): void {
    $order = Order::factory()->create();
    packedOrderItem($order, 'کرم', 2, 120, fits('small'));
    packedOrderItem($order, 'شامپو', 1, 250, fits('medium'), liquid: true);

    app(MarkOrderPaid::class)->handle(new PaymentSucceeded($order->getId(), 1));

    $parcel = OrderParcel::query()->sole();

    expect($order->refresh()->getStatus())->toBe(OrderStatus::Paid)
        ->and($parcel->getBoxSnapshot()['name'] ?? null)->toBe('کارتن سایز 2')
        ->and($parcel->getItemsWeightG())->toBe(490)
        ->and($parcel->getTotalWeightG())->toBe(490 + tipaxBox(2)->getEmptyWeightG())
        ->and($parcel->getContentType()->tapinProductType())->toBe(4)
        ->and(array_column($parcel->getItems(), 'quantity'))->toBe([2, 1]);
});

it('splits a heavy order into parcels of at most 6 kg', function (): void {
    $order = Order::factory()->create();
    packedOrderItem($order, 'مکمل', 4, 2_000, fits('large'));

    app(MarkOrderPaid::class)->handle(new PaymentSucceeded($order->getId(), 1));

    $weights = OrderParcel::query()->orderBy(OrderParcel::COLUMN_SEQUENCE)->pluck(OrderParcel::COLUMN_TOTAL_WEIGHT_G)->all();

    expect($weights)->toHaveCount(2)->each->toBeLessThanOrEqual(6_000);
});

it('shows the parcels on the admin order page and repacks with today\'s boxes', function (): void {
    Filament::setCurrentPanel('admin');
    $this->seed(RolesSeeder::class);
    $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::Operator)->create(), 'admin');
    $order = Order::factory()->create();
    packedOrderItem($order, 'کرم', 2, 120, fits('small'));
    app(MarkOrderPaid::class)->handle(new PaymentSucceeded($order->getId(), 1));

    ShippingBox::query()->where(ShippingBox::COLUMN_TIPAX_SIZE, 1)->update([ShippingBox::COLUMN_IS_ACTIVE => false]);
    $before = OrderParcel::query()->sole()->getBoxSnapshot()['name'] ?? null;

    livewire(ViewOrder::class, ['record' => $order->refresh()->getRouteKey()])
        ->assertSee(['بسته‌ها', 'بسته ۱', 'کرم × ۲'])
        ->callAction(TestAction::make('repack')->schemaComponent('parcels', 'infolist'));

    expect($before)->toBe('کارتن سایز 1')
        ->and(OrderParcel::query()->sole()->getBoxSnapshot()['name'] ?? null)->toBe('کارتن سایز 2');
});
