<?php

declare(strict_types=1);

use App\Modules\Identity\Models\Customer;
use App\Modules\Orders\Actions\ChangeOrderStatus;
use App\Modules\Orders\Enums\OrderActor;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Livewire\Account\OrderDetail;
use App\Modules\Orders\Livewire\TrackOrder;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\RateLimiter;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    RateLimiter::clear('track-order:127.0.0.1');
    $this->order = Order::factory()->paid()->create([
        Order::COLUMN_NUMBER => 'PH-140508-000123',
        Order::COLUMN_CUSTOMER_SNAPSHOT => ['name' => 'سارا رضایی', 'mobile' => '09121112233'],
        Order::COLUMN_ADDRESS_SNAPSHOT => ['title' => 'خانه', 'main_street' => 'ولیعصر', 'receiver_name' => 'مادر سارا', 'receiver_mobile' => '09124445566', 'postal_code' => '1234567890'],
    ]);
    $this->order->setStatus(OrderStatus::Processing)->setTrackingCode(null)->save();
    app(ChangeOrderStatus::class)($this->order, OrderStatus::OnTheWay, OrderActor::Admin, 1);
    $this->order->setTrackingCode('TPX-778812')->save();
});

it('shows the status, history and tracking code to whoever has the number and the customer\'s or receiver\'s mobile', function (string $mobile): void {
    $this->get(route('orders.track'))->assertOk()->assertSee('پیگیری سفارش');

    livewire(TrackOrder::class)
        ->set('number', 'ph-۱۴۰۵۰۸-۰۰۰۱۲۳')
        ->set('mobile', $mobile)
        ->call('track')
        ->assertHasNoErrors()
        ->assertSee('در راه')
        ->assertSee('TPX-778812')
        ->assertDontSee('ولیعصر');
})->with(['customer' => ['۰۹۱۲۱۱۱۲۲۳۳'], 'receiver' => ['09124445566']]);

it('gives the same answer for a wrong number and a wrong mobile', function (string $number, string $mobile): void {
    livewire(TrackOrder::class)
        ->set('number', $number)
        ->set('mobile', $mobile)
        ->call('track')
        ->assertHasErrors(['number'])
        ->assertSee('سفارشی با این شماره و موبایل پیدا نشد.')
        ->assertDontSee('TPX-778812');
})->with([
    'wrong mobile' => ['PH-140508-000123', '09129999999'],
    'wrong number' => ['PH-140508-000999', '09121112233'],
]);

it('allows ten tries a minute', function (): void {
    $component = livewire(TrackOrder::class)->set('number', 'PH-140508-000999')->set('mobile', '09121112233');

    foreach (range(1, TrackOrder::MAX_TRIES_PER_MINUTE) as $try) {
        $component->call('track');
    }

    $component->set('number', 'PH-140508-000123')->call('track')
        ->assertSee('تلاش‌های شما زیاد بود')
        ->assertDontSee('TPX-778812');
});

it('shows the tracking code on the customer\'s order page', function (): void {
    $customer = Customer::query()->findOrFail($this->order->getCustomerId());
    $this->actingAs($customer, 'customer');

    livewire(OrderDetail::class, ['order' => $this->order->getId()])->assertSee('TPX-778812');
});
