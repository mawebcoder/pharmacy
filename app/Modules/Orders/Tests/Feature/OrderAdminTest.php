<?php

declare(strict_types=1);

use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Modules\Identity\Models\AdminUser;
use App\Modules\Identity\Models\Customer;
use App\Modules\Orders\Actions\MarkOrderRefunded;
use App\Modules\Orders\Enums\OrderActor;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Enums\SourcingStatus;
use App\Modules\Orders\Exceptions\OrderRefused;
use App\Modules\Orders\Filament\RelationManagers\CustomerOrdersRelationManager;
use App\Modules\Orders\Filament\Resources\Orders\OrderResource;
use App\Modules\Orders\Filament\Resources\Orders\Pages\ListOrders;
use App\Modules\Orders\Filament\Resources\Orders\Pages\ViewOrder;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Models\OrderStatusLog;
use App\Modules\Payments\Enums\PaymentAttemptStatus;
use App\Modules\Payments\Filament\RelationManagers\OrderPaymentsRelationManager;
use App\Modules\Payments\Models\Payment;
use App\Modules\Shared\Support\Toman;
use Filament\Facades\Filament;
use Filament\Tables\Filters\SelectFilter;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->seed(RolesSeeder::class);
});

function adminWith(AdminRole $role): AdminUser
{
    return AdminUser::factory()->withTwoFactorAuthentication()->withRole($role)->create();
}

/**
 * One item copied onto the order, as PlaceOrder does: 2 × 600,000 rial, bought at 450,000 each.
 */
function orderItem(Order $order, string $name = 'کرم اوره'): OrderItem
{
    $item = (new OrderItem)
        ->setOrderId($order->getId())
        ->setProductName($name)
        ->setSku('EUC-000101-01')
        ->setQuantity(2)
        ->setProductSnapshot(['name' => $name])
        ->setProviderName('پخش سینا')
        ->setUnitCost(450_000)
        ->setListPrice(600_000)
        ->setUnitPrice(600_000)
        ->setPaidTotal(1_200_000);
    $item->save();

    return $item;
}

/**
 * A cancelled order owed back: 12,500,000 rial of products less a 154,325 rial coupon, plus 500,000 rial shipping.
 */
function refundableOrder(): Order
{
    return Order::factory()->paid()->create([
        Order::COLUMN_STATUS => OrderStatus::Cancelled,
        Order::COLUMN_PAYMENT_STATUS => PaymentStatus::RefundPending,
        Order::COLUMN_SUBTOTAL => 12_500_000,
        Order::COLUMN_DISCOUNT_TOTAL => 154_325,
        Order::COLUMN_SHIPPING_TOTAL => 500_000,
        Order::COLUMN_TOTAL => 12_845_675,
    ]);
}

function payment(Order $order, PaymentAttemptStatus $status, string $authority): Payment
{
    $payment = (new Payment)
        ->setOrderId($order->getId())
        ->setGateway('fake')
        ->setAmount($order->getTotal())
        ->setAuthority($authority)
        ->setUrl('https://gateway.test/'.$authority)
        ->setStatus($status)
        ->setExpiresAt(now()->addMinutes(30));
    $payment->save();

    return $payment;
}

describe('as an operator', function (): void {
    beforeEach(function (): void {
        $this->admin = adminWith(AdminRole::Operator);
        $this->actingAs($this->admin, 'admin');
    });

    it('lists orders and finds them by number or mobile', function (): void {
        $mine = Order::factory()->paid()->create([Order::COLUMN_NUMBER => 'PH-140508-000123', Order::COLUMN_CUSTOMER_SNAPSHOT => ['name' => 'سارا', 'mobile' => '09121112233']]);
        $other = Order::factory()->paid()->create([Order::COLUMN_NUMBER => 'PH-140508-000999', Order::COLUMN_CUSTOMER_SNAPSHOT => ['name' => 'علی', 'mobile' => '09125556677']]);

        $this->get(OrderResource::getUrl('index'))->assertOk();
        livewire(ListOrders::class)->searchTable('000123')->assertCanSeeTableRecords([$mine])->assertCanNotSeeTableRecords([$other]);
        livewire(ListOrders::class)->searchTable('۰۹۱۲۱۱۱۲۲۳۳')->assertCanSeeTableRecords([$mine])->assertCanNotSeeTableRecords([$other]);
    });

    it('shows the order from its copies, with what we pay the supplier and the margin', function (): void {
        $order = Order::factory()->paid()->create([Order::COLUMN_CUSTOMER_SNAPSHOT => ['name' => 'سارا رضایی', 'mobile' => '09123456789']]);
        orderItem($order, 'کرم اوره ۱۰٪');

        // 1,200,000 − 2 × 450,000 = 300,000 rial.
        $this->get(OrderResource::getUrl('view', ['record' => $order]))
            ->assertOk()
            ->assertSee('کرم اوره ۱۰٪')
            ->assertSee('پخش سینا')
            ->assertSee('سارا رضایی')
            ->assertSee(Toman::format(300_000));
    });

    it('labels the status and payment filters in Persian', function (): void {
        livewire(ListOrders::class)
            ->assertTableFilterExists(Order::COLUMN_STATUS, fn (SelectFilter $filter): bool => $filter->getOptions() === OrderStatus::options()
                && in_array('پرداخت‌شده', $filter->getOptions(), true))
            ->assertTableFilterExists(Order::COLUMN_PAYMENT_STATUS, fn (SelectFilter $filter): bool => $filter->getOptions() === PaymentStatus::options()
                && in_array('در انتظار بازگشت وجه', $filter->getOptions(), true));
    });

    it('dates orders by their payment and filters by it, and shows the shipping price', function (): void {
        $paidToday = Order::factory()->paid()->create([Order::COLUMN_PLACED_AT => now()->subDays(3), Order::COLUMN_SHIPPING_TOTAL => 450_000]);
        $paidBefore = Order::factory()->paid()->create([Order::COLUMN_PLACED_AT => now()->subDays(3), Order::COLUMN_PAID_AT => now()->subDays(3)]);

        livewire(ListOrders::class)
            ->assertTableColumnExists(Order::COLUMN_PAID_AT)
            ->filterTable('paid_between', ['from' => now()->toDateString()])
            ->assertCanSeeTableRecords([$paidToday])
            ->assertCanNotSeeTableRecords([$paidBefore]);
        $this->get(OrderResource::getUrl('view', ['record' => $paidToday]))->assertOk()->assertSee(Toman::format(450_000));
    });

    it('shows the shipping tracking code and the payment reference on the order page', function (): void {
        $order = Order::factory()->paid()->create([Order::COLUMN_TRACKING_CODE => 'TPX-778812']);
        payment($order, PaymentAttemptStatus::Paid, 'A0000000000000000000000000000001')->setRefId('88231044')->save();

        $this->get(OrderResource::getUrl('view', ['record' => $order]))
            ->assertOk()
            ->assertSee(['کد رهگیری مرسوله', 'TPX-778812', 'کد پیگیری پرداخت', '88231044']);
    });

    it('offers only the moves allowed now and logs them with the admin', function (): void {
        $order = Order::factory()->paid()->create();

        livewire(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionVisible('startProcessing')
            ->assertActionVisible('cancel')
            ->assertActionHidden('sendOnItsWay')
            ->assertActionHidden('markDelivered')
            ->callAction('startProcessing')
            ->assertNotified('وضعیت سفارش به‌روز شد.');

        expect($order->refresh()->getStatus())->toBe(OrderStatus::Processing)
            ->and(OrderStatusLog::query()->latest('id')->first())
            ->getActorType()->toBe(OrderActor::Admin)
            ->getActorId()->toBe($this->admin->getKey());
    });

    it('sends a prepared order on its way with its tracking code, then marks it delivered', function (): void {
        $order = Order::factory()->paid()->create();
        // Its purchase document has fully arrived (S25).
        $order->setStatus(OrderStatus::Processing)->setSourcingStatus(SourcingStatus::ReadyToPack)->save();

        livewire(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('sendOnItsWay', ['tracking_code' => ''])
            ->assertHasActionErrors(['tracking_code' => 'required']);
        livewire(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('sendOnItsWay', ['tracking_code' => 'TPX-778812'])
            ->assertHasNoActionErrors();

        expect($order->refresh())->getStatus()->toBe(OrderStatus::OnTheWay)->getTrackingCode()->toBe('TPX-778812');

        livewire(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionHidden('cancel')
            ->callAction('markDelivered');

        expect($order->refresh())->getStatus()->toBe(OrderStatus::Delivered)->getDeliveredAt()->not->toBeNull();
    });

    it('cancels a paid, processing or delivered order with a reason and marks the payment for refund', function (OrderStatus $status): void {
        $order = Order::factory()->paid()->create();
        $order->setStatus($status)->save();

        livewire(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('cancel', ['reason' => ''])
            ->assertHasActionErrors(['reason' => 'required']);
        livewire(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('cancel', ['reason' => 'موجودی تأمین‌کننده تمام شد']);

        expect($order->refresh())
            ->getStatus()->toBe(OrderStatus::Cancelled)
            ->getPaymentStatus()->toBe(PaymentStatus::RefundPending)
            ->and(OrderStatusLog::query()->latest('id')->first()?->getNote())->toBe('موجودی تأمین‌کننده تمام شد');
    })->with([OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Delivered]);

    it('cannot move an order the system owns, or record a refund', function (): void {
        $pending = Order::factory()->create();
        $refundPending = Order::factory()->paid()->create([Order::COLUMN_STATUS => OrderStatus::Cancelled, Order::COLUMN_PAYMENT_STATUS => PaymentStatus::RefundPending]);

        livewire(ViewOrder::class, ['record' => $pending->getRouteKey()])
            ->assertActionHidden('startProcessing')
            ->assertActionHidden('cancel');
        livewire(ViewOrder::class, ['record' => $refundPending->getRouteKey()])
            ->assertActionHidden('markRefunded');
    });

    it('lists only the order\'s payment attempts', function (): void {
        $order = Order::factory()->paid()->create();
        $failed = payment($order, PaymentAttemptStatus::Failed, 'ATTEMPT-1');
        $paid = payment($order, PaymentAttemptStatus::Paid, 'ATTEMPT-2');
        $elsewhere = payment(Order::factory()->create(), PaymentAttemptStatus::Pending, 'OTHER-ORDER');

        livewire(OrderPaymentsRelationManager::class, ['ownerRecord' => $order, 'pageClass' => ViewOrder::class])
            ->assertCanSeeTableRecords([$failed, $paid])
            ->assertCanNotSeeTableRecords([$elsewhere]);
    });

    it('lists a customer\'s orders on their page, each opening its order in a new tab', function (): void {
        $customer = Customer::factory()->create();
        $theirs = Order::factory()->paid()->create([Order::COLUMN_CUSTOMER_ID => $customer->getKey()]);
        $someoneElses = Order::factory()->paid()->create();

        livewire(CustomerOrdersRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])
            ->assertCanSeeTableRecords([$theirs])
            ->assertCanNotSeeTableRecords([$someoneElses])
            ->assertSeeHtml('href="'.e(OrderResource::getUrl('view', ['record' => $theirs])).'"')
            ->assertSeeHtml('target="_blank"');
    });
});

describe('as a super admin', function (): void {
    beforeEach(function (): void {
        $this->actingAs(adminWith(AdminRole::SuperAdmin), 'admin');
    });

    it('records a full refund of a cancelled order by default, with its reference', function (): void {
        $order = refundableOrder();

        livewire(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->mountAction('markRefunded')
            ->assertActionDataSet(['items_amount' => 1_234_568, 'shipping_amount' => 50_000])
            ->fillForm(['reference' => 'REF-88231'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        expect($order->refresh())
            ->getPaymentStatus()->toBe(PaymentStatus::Refunded)
            ->getRefundReference()->toBe('REF-88231')
            ->getRefundItemsAmount()->toBe(12_345_675)
            ->getRefundShippingAmount()->toBe(500_000)
            ->getRefundedAt()->not->toBeNull();
    });

    it('holds back the free shipping from the product refund when the order had been delivered', function (bool $delivered, int $itemsToman, int $itemsRial): void {
        $order = refundableOrder();
        $order->setShippingPrice(500_000)->setShippingDiscount(500_000)->setShippingDiscountReason('coupon')->setShippingTotal(0)
            ->setTotal(12_345_675)->setDeliveredAt($delivered ? now() : null)->save();

        livewire(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->mountAction('markRefunded')
            ->assertActionDataSet(['items_amount' => $itemsToman, 'shipping_amount' => 0])
            ->fillForm(['reference' => 'REF-7'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        expect($order->refresh())->getRefundItemsAmount()->toBe($itemsRial)->getRefundShippingAmount()->toBe(0);
    })->with([
        'delivered, then cancelled' => [true, 1_184_568, 11_845_675],
        'cancelled before delivery' => [false, 1_234_568, 12_345_675],
    ]);

    it('records a partial refund for the products and for shipping', function (): void {
        $order = refundableOrder();

        livewire(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('markRefunded', ['items_amount' => '600,000', 'shipping_amount' => '0', 'reference' => 'REF-1'])
            ->assertHasNoActionErrors();

        expect($order->refresh())->getRefundItemsAmount()->toBe(6_000_000)->getRefundShippingAmount()->toBe(0);
    });

    it('refuses refunds above what was paid, or of nothing', function (int $items, int $shipping): void {
        $order = refundableOrder();

        expect(fn () => app(MarkOrderRefunded::class)($order, 'REF-1', $items, $shipping))->toThrow(OrderRefused::class)
            ->and($order->refresh()->getPaymentStatus())->toBe(PaymentStatus::RefundPending);
    })->with([
        'products' => [12_345_676, 0],
        'shipping' => [0, 500_001],
        'nothing' => [0, 0],
    ]);

    it('refuses a refund for an order that owes nothing back', function (): void {
        $order = Order::factory()->paid()->create();

        expect(fn () => app(MarkOrderRefunded::class)($order, 'REF-1', 1, 0))->toThrow(OrderRefused::class);

        expect($order->refresh()->getPaymentStatus())->toBe(PaymentStatus::Paid);
    });
});

it('keeps content editors out of orders', function (): void {
    $this->actingAs(adminWith(AdminRole::ContentEditor), 'admin');

    $this->get(OrderResource::getUrl('index'))->assertForbidden();
});
