<?php

declare(strict_types=1);

use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Modules\Identity\Models\AdminUser;
use App\Modules\Identity\Models\Customer;
use App\Modules\Orders\Actions\CancelOrderByAdmin;
use App\Modules\Orders\Enums\OrderActor;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Enums\SourcingStatus;
use App\Modules\Orders\Filament\RelationManagers\CustomerOrdersRelationManager;
use App\Modules\Orders\Filament\Resources\Orders\OrderResource;
use App\Modules\Orders\Filament\Resources\Orders\Pages\ListOrders;
use App\Modules\Orders\Filament\Resources\Orders\Pages\ViewOrder;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Models\OrderStatusLog;
use App\Modules\Payments\Actions\ApproveRefund;
use App\Modules\Payments\Enums\PaymentAttemptStatus;
use App\Modules\Payments\Enums\PaymentKind;
use App\Modules\Payments\Enums\RefundSource;
use App\Modules\Payments\Exceptions\RefundRefused;
use App\Modules\Payments\Filament\RelationManagers\OrderPaymentsRelationManager;
use App\Modules\Payments\Filament\Resources\Refunds\Pages\ViewRefund;
use App\Modules\Payments\Models\Payment;
use App\Modules\Shared\Support\Toman;
use Filament\Facades\Filament;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

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
 * A paid order cancelled by staff, owed back (owner, 2026-11-19: its refund record opens for approval): 12,500,000 rial
 * of products less a 154,325 rial coupon, plus 500,000 rial shipping, paid at the gateway.
 */
function refundableOrder(array $overrides = []): Order
{
    $order = Order::factory()->paid()->create([
        Order::COLUMN_STATUS => OrderStatus::Paid,
        Order::COLUMN_SUBTOTAL => 12_500_000,
        Order::COLUMN_DISCOUNT_TOTAL => 154_325,
        Order::COLUMN_SHIPPING_TOTAL => 500_000,
        Order::COLUMN_TOTAL => 12_845_675,
        ...$overrides,
    ]);
    payment($order, PaymentAttemptStatus::Paid, 'A-'.$order->getId());
    app(CancelOrderByAdmin::class)($order, 1, 'ناموجود');

    return $order->refresh();
}

function refundOf(Order $order): Payment
{
    return Payment::query()->where(Payment::COLUMN_ORDER_ID, $order->getId())->where(Payment::COLUMN_KIND, PaymentKind::Refund)->sole();
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
            ->callAction('sendOnItsWayManually', ['tracking_code' => ''])
            ->assertHasActionErrors(['tracking_code' => 'required']);
        livewire(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('sendOnItsWayManually', ['tracking_code' => 'TPX-778812'])
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

    it('cannot move an order the system owns, or approve a refund', function (): void {
        $pending = Order::factory()->create();
        $refundPending = refundableOrder();

        livewire(ViewOrder::class, ['record' => $pending->getRouteKey()])
            ->assertActionHidden('startProcessing')
            ->assertActionHidden('cancel');
        livewire(ViewRefund::class, ['record' => refundOf($refundPending)->getRouteKey()])
            ->assertActionHidden('approve')
            ->assertActionHidden('reject');
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

    it('confirms a pending Zarinpal payment with «استعلام از زرین‌پال», as the callback would', function (): void {
        config([
            'payments.gateway' => 'zarinpal',
            'payments.zarinpal.merchant_id' => '00000000-1111-2222-3333-444444444444',
            'payments.zarinpal.sandbox_base_url' => 'https://sandbox.zarinpal.test',
        ]);
        Http::fake(['sandbox.zarinpal.test/pg/v4/payment/verify.json' => Http::response(['data' => ['code' => 100, 'message' => 'Paid', 'ref_id' => 8246301, 'card_pan' => '502229******5995', 'card_hash' => 'AB', 'fee_type' => 'Merchant', 'fee' => 2300], 'errors' => []])]);
        $order = Order::factory()->create();
        $pending = payment($order, PaymentAttemptStatus::Pending, 'S00000000000000000000000000000000rck');
        $pending->setGateway('zarinpal')->save();

        livewire(OrderPaymentsRelationManager::class, ['ownerRecord' => $order, 'pageClass' => ViewOrder::class])
            ->assertSee('S00000000000000')
            ->callTableAction('recheck', $pending)
            ->assertNotified('پرداخت تأیید شد.');

        expect($pending->refresh())
            ->getStatus()->toBe(PaymentAttemptStatus::Paid)
            ->getRefId()->toBe('8246301')
            ->and($order->refresh()->getPaymentStatus())->toBe(PaymentStatus::Paid);
        Http::assertSent(fn (Request $request): bool => $request['amount'] === $order->getTotal());
    });

    it('offers «استعلام از زرین‌پال» only on pending Zarinpal payments', function (): void {
        $order = Order::factory()->paid()->create();
        $paid = payment($order, PaymentAttemptStatus::Paid, 'S00000000000000000000000000000000pd1');
        $paid->setGateway('zarinpal')->save();
        $fake = payment($order, PaymentAttemptStatus::Pending, 'FAKE-PENDING');

        livewire(OrderPaymentsRelationManager::class, ['ownerRecord' => $order, 'pageClass' => ViewOrder::class])
            ->assertTableActionHidden('recheck', $paid)
            ->assertTableActionHidden('recheck', $fake);
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

    it('opens the refund of a cancelled paid order for approval, and settles the order when approved in full', function (): void {
        $order = refundableOrder();
        $refund = refundOf($order);

        expect($refund)->getStatus()->toBe(PaymentAttemptStatus::Pending)->getAmount()->toBe(12_845_675)->getRefundSource()->toBe(RefundSource::OrderCancel)
            ->getDetails()->toBe(['items' => 12_345_675, 'shipping' => 500_000]);

        livewire(ViewRefund::class, ['record' => $refund->getRouteKey()])
            ->mountAction('approve')
            ->assertActionDataSet(['items' => 1_234_568, 'shipping' => 50_000])
            ->fillForm(['reference' => 'REF-88231'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        expect($order->refresh())
            ->getPaymentStatus()->toBe(PaymentStatus::Refunded)
            ->getRefundReference()->toBe('REF-88231')
            ->getRefundItemsAmount()->toBe(12_345_675)
            ->getRefundShippingAmount()->toBe(500_000)
            ->getRefundedAt()->not->toBeNull()
            ->and($refund->refresh()->getStatus())->toBe(PaymentAttemptStatus::Paid);
    });

    it('holds back the free shipping from the product refund when the order had been delivered', function (bool $delivered, int $itemsRial): void {
        $order = refundableOrder([
            Order::COLUMN_SHIPPING_PRICE => 500_000, Order::COLUMN_SHIPPING_DISCOUNT => 500_000, Order::COLUMN_SHIPPING_DISCOUNT_REASON => 'coupon',
            Order::COLUMN_SHIPPING_TOTAL => 0, Order::COLUMN_TOTAL => 12_345_675, Order::COLUMN_DELIVERED_AT => $delivered ? now() : null,
        ]);

        expect(refundOf($order)->getDetails())->toBe(['items' => $itemsRial, 'shipping' => 0]);
    })->with([
        'delivered, then cancelled' => [true, 11_845_675],
        'cancelled before delivery' => [false, 12_345_675],
    ]);

    it('approves a lower refund for the products and for shipping', function (): void {
        $order = refundableOrder();

        livewire(ViewRefund::class, ['record' => refundOf($order)->getRouteKey()])
            ->callAction('approve', ['items' => '600,000', 'shipping' => '0', 'reference' => 'REF-1'])
            ->assertHasNoActionErrors();

        expect($order->refresh())->getRefundItemsAmount()->toBe(6_000_000)->getRefundShippingAmount()->toBe(0)
            ->and(refundOf($order)->getAmount())->toBe(6_000_000);
    });

    it('refuses approving more than was owed, or nothing', function (int $items, int $shipping): void {
        $order = refundableOrder();

        expect(fn () => app(ApproveRefund::class)(refundOf($order), 'REF-1', 1, $items, $shipping))->toThrow(RefundRefused::class)
            ->and($order->refresh()->getPaymentStatus())->toBe(PaymentStatus::RefundPending);
    })->with([
        'products' => [12_345_676, 0],
        'shipping' => [0, 500_001],
        'nothing' => [0, 0],
    ]);

    it('rejects a refund with a reason, and never decides it twice', function (): void {
        $order = refundableOrder();

        livewire(ViewRefund::class, ['record' => refundOf($order)->getRouteKey()])
            ->callAction('reject', ['reason' => 'ثبت اشتباه'])
            ->assertNotified('بازگشت وجه رد شد.')
            ->assertActionHidden('approve');

        expect(refundOf($order))->getStatus()->toBe(PaymentAttemptStatus::Failed)->getFailureReason()->toBe('ثبت اشتباه')
            ->and(fn () => app(ApproveRefund::class)(refundOf($order), 'REF-1', 1))->toThrow(RefundRefused::class);
    });

    it('opens no refund for an order that owes nothing back', function (): void {
        Order::factory()->paid()->create();

        expect(Payment::query()->where(Payment::COLUMN_KIND, PaymentKind::Refund)->count())->toBe(0);
    });
});
