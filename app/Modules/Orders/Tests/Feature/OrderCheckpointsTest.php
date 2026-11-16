<?php

declare(strict_types=1);

use App\Modules\Identity\Models\Customer;
use App\Modules\Orders\Actions\ChangeOrderStatus;
use App\Modules\Orders\Data\OrderCheckpointData;
use App\Modules\Orders\Enums\CheckpointState;
use App\Modules\Orders\Enums\OrderActor;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Livewire\Account\OrderDetail;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Support\OrderCheckpoints;

use function Pest\Livewire\livewire;

/**
 * @param  list<OrderStatus>  $path  the statuses the order moves through after being paid
 * @return list<array{OrderStatus, CheckpointState}>
 */
function checkpointsAfter(array $path, OrderActor $actor = OrderActor::Admin): array
{
    $order = Order::factory()->paid()->create();
    ChangeOrderStatus::log($order->getId(), OrderStatus::Pending, OrderStatus::Paid, OrderActor::System);

    foreach ($path as $status) {
        app(ChangeOrderStatus::class)($order, $status, $actor, 1);
    }

    return array_map(
        fn (OrderCheckpointData $checkpoint): array => [$checkpoint->status, $checkpoint->state],
        OrderCheckpoints::for($order->fresh()->load('statusLogs')),
    );
}

it('ticks the passed checkpoints, marks the order\'s status as current and the rest as waiting', function (array $path, array $expected): void {
    expect(checkpointsAfter($path))->toBe($expected);
})->with([
    'paid' => [[], [
        [OrderStatus::Paid, CheckpointState::Current],
        [OrderStatus::Processing, CheckpointState::Upcoming],
        [OrderStatus::OnTheWay, CheckpointState::Upcoming],
        [OrderStatus::Delivered, CheckpointState::Upcoming],
    ]],
    'on the way' => [[OrderStatus::Processing, OrderStatus::OnTheWay], [
        [OrderStatus::Paid, CheckpointState::Passed],
        [OrderStatus::Processing, CheckpointState::Passed],
        [OrderStatus::OnTheWay, CheckpointState::Current],
        [OrderStatus::Delivered, CheckpointState::Upcoming],
    ]],
    'delivered' => [[OrderStatus::Processing, OrderStatus::OnTheWay, OrderStatus::Delivered], [
        [OrderStatus::Paid, CheckpointState::Passed],
        [OrderStatus::Processing, CheckpointState::Passed],
        [OrderStatus::OnTheWay, CheckpointState::Passed],
        [OrderStatus::Delivered, CheckpointState::Passed],
    ]],
]);

it('keeps the checkpoints a cancelled order passed and ends on the cancellation', function (array $path, array $expected): void {
    expect(checkpointsAfter($path))->toBe($expected);
})->with([
    'cancelled when paid' => [[OrderStatus::Cancelled], [
        [OrderStatus::Paid, CheckpointState::Passed],
        [OrderStatus::Cancelled, CheckpointState::Current],
    ]],
    'cancelled while processing' => [[OrderStatus::Processing, OrderStatus::Cancelled], [
        [OrderStatus::Paid, CheckpointState::Passed],
        [OrderStatus::Processing, CheckpointState::Passed],
        [OrderStatus::Cancelled, CheckpointState::Current],
    ]],
]);

it('waits on every checkpoint before payment and shows none for an expired order', function (): void {
    $pending = OrderCheckpoints::for(Order::factory()->create()->load('statusLogs'));

    expect(array_map(fn (OrderCheckpointData $checkpoint): CheckpointState => $checkpoint->state, $pending))
        ->toBe(array_fill(0, 4, CheckpointState::Upcoming))
        ->and(OrderCheckpoints::for(Order::factory()->expired()->create()->load('statusLogs')))->toBe([]);
});

it('shows the tracker on the customer\'s order page with the current checkpoint marked', function (): void {
    $order = Order::factory()->paid()->create();
    app(ChangeOrderStatus::class)($order, OrderStatus::Processing, OrderActor::Admin, 1);
    $this->actingAs(Customer::query()->findOrFail($order->getCustomerId()), 'customer');

    livewire(OrderDetail::class, ['order' => $order->getId()])
        ->assertSeeInOrder(['روند سفارش', 'پرداخت‌شده', 'در حال آماده‌سازی', 'در جریان', 'در راه', 'در انتظار', 'تحویل شد'])
        ->assertSee('aria-current="step"', false);
});
