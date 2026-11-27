<?php

declare(strict_types=1);

use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Queries\CustomerInbox;
use App\Modules\Notifications\Listeners\SendOrderSms;
use App\Modules\Notifications\Listeners\SendPaymentLinkSms;
use App\Modules\Notifications\Listeners\SendRefundSms;
use App\Modules\Notifications\Listeners\SendReturnRequestSms;
use App\Modules\Notifications\Messages\CustomerMessage;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Events\OrderCancelled;
use App\Modules\Orders\Events\OrderOnItsWay;
use App\Modules\Orders\Events\OrderPaid;
use App\Modules\Orders\Events\OrderPaymentLinkRequested;
use App\Modules\Orders\Events\OrderReturnRegistered;
use App\Modules\Orders\Events\OrderReturnRequested;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Events\RefundApproved;
use App\Modules\Profile\Livewire\Notifications;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Queue;

use function Pest\Livewire\livewire;

/*
| «اعلان‌ها» in the account (roadmap S37, owner 2026-11-21): every SMS sent to a customer is kept as a notification
| linked to its order; unread ones show as a badge; opening one or «همه خوانده شد» marks them read.
*/

beforeEach(function (): void {
    Queue::fake();
    $this->customer = Customer::factory()->create();
    $this->order = Order::factory()->paid()->create([
        Order::COLUMN_CUSTOMER_ID => $this->customer->getKey(),
        Order::COLUMN_NUMBER => 'PH-140508-000123',
        Order::COLUMN_TRACKING_CODE => '940512345678901234',
        Order::COLUMN_CUSTOMER_SNAPSHOT => ['name' => 'سارا رضایی', 'mobile' => '09123456789'],
    ]);
});

/**
 * Keeps one notification for the customer, as an order SMS would.
 */
function notifyCustomer(Customer $customer, string $title = 'سفارش پرداخت شد', string $url = '/account/orders/1'): void
{
    $customer->notify(new CustomerMessage('order_paid', $title, 'متن', $url));
}

it('keeps a notification with the SMS text, linked to the order, for each SMS sent to the customer', function (Closure $send, string $template, string $title): void {
    $send($this->order);

    $notification = DatabaseNotification::query()->sole();

    expect($notification->notifiable_id)->toBe($this->customer->getKey())
        ->and($notification->read_at)->toBeNull()
        ->and($notification->data['template'])->toBe($template)
        ->and($notification->data['title'])->toBe($title)
        ->and($notification->data['body'])->toContain('PH-140508-000123')
        ->and($notification->data['url'])->toBe(route('account.orders.show', $this->order->getId()));
})->with([
    'paid' => [fn (Order $order) => app(SendOrderSms::class)->handle(new OrderPaid($order->getId())), 'order_paid', 'سفارش پرداخت شد'],
    'on its way' => [fn (Order $order) => app(SendOrderSms::class)->handle(new OrderOnItsWay($order->getId())), 'order_on_the_way', 'سفارش ارسال شد'],
    'cancelled' => [fn (Order $order) => app(SendOrderSms::class)->handle(new OrderCancelled($order->getId())), 'order_cancelled', 'سفارش لغو شد'],
    'return' => [fn (Order $order) => app(SendOrderSms::class)->handle(new OrderReturnRegistered($order->getId(), '940598765432109876')), 'order_return', 'مرجوعی ثبت شد'],
    'refund' => [fn (Order $order) => app(SendRefundSms::class)->handle(new RefundApproved(1, $order->getId(), null, 1_250_000, 'REF-1')), 'refund_paid', 'بازگشت وجه'],
    'payment link' => [function (Order $order): void {
        $order->setStatus(OrderStatus::Pending)->setPaymentStatus(PaymentStatus::Pending)->setExpiresAt(now()->addHour())->save();
        app(SendPaymentLinkSms::class)->handle(new OrderPaymentLinkRequested($order->getId()));
    }, 'order_payment_link', 'پرداخت سفارش'],
]);

it('keeps nothing for the customer when staff are texted about a return request', function (): void {
    app(SendReturnRequestSms::class)->handle(new OrderReturnRequested($this->order->getId(), 'R-1'));

    expect(DatabaseNotification::query()->count())->toBe(0);
});

it('counts only the customer\'s unread notifications for the badges', function (): void {
    notifyCustomer($this->customer);
    notifyCustomer($this->customer);
    notifyCustomer(Customer::factory()->create());
    $this->customer->notifications()->first()?->markAsRead();

    expect(app(CustomerInbox::class)->unreadCount($this->customer->getKey()))->toBe(1);

    $this->actingAs($this->customer, 'customer')->get(route('account.orders'))
        ->assertOk()
        ->assertSee('1 اعلان خوانده نشده')
        ->assertSee('aria-label="1 خوانده نشده"', false);
});

it('lists only the customer\'s own notifications, newest first', function (): void {
    notifyCustomer($this->customer, 'اولی');
    $this->travel(1)->minutes();
    notifyCustomer($this->customer, 'دومی');
    notifyCustomer(Customer::factory()->create(), 'مال دیگری');

    $this->actingAs($this->customer, 'customer')->get(route('account.notifications'))
        ->assertOk()
        ->assertSeeInOrder(['دومی', 'اولی'])
        ->assertDontSee('مال دیگری');
});

it('marks a notification read when opened and goes to its order', function (): void {
    notifyCustomer($this->customer, url: '/account/orders/57');
    $id = (string) $this->customer->notifications()->sole()->getKey();

    $this->actingAs($this->customer, 'customer');
    livewire(Notifications::class)->call('open', $id)->assertRedirect('/account/orders/57');

    expect($this->customer->unreadNotifications()->count())->toBe(0);
});

it('never opens another customer\'s notification', function (): void {
    $other = Customer::factory()->create();
    notifyCustomer($other);
    $id = (string) $other->notifications()->sole()->getKey();

    $this->actingAs($this->customer, 'customer');
    livewire(Notifications::class)->call('open', $id)->assertNoRedirect();

    expect($other->unreadNotifications()->count())->toBe(1);
});

it('marks all of the customer\'s notifications read, and no one else\'s', function (): void {
    notifyCustomer($this->customer);
    notifyCustomer($this->customer);
    $other = Customer::factory()->create();
    notifyCustomer($other);

    $this->actingAs($this->customer, 'customer');
    livewire(Notifications::class)->assertSee('همه خوانده شد')->call('markAllRead')->assertDontSee('همه خوانده شد');

    expect($this->customer->unreadNotifications()->count())->toBe(0)
        ->and($other->unreadNotifications()->count())->toBe(1);
});
