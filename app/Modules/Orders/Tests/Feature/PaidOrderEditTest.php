<?php

declare(strict_types=1);

use App\Modules\Catalog\Enums\StockStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Checkout\Actions\EditPaidOrder;
use App\Modules\Checkout\Actions\PlaceOrderForCustomer;
use App\Modules\Checkout\Data\PaidOrderEditData;
use App\Modules\Checkout\Data\PanelOrderData;
use App\Modules\Checkout\Exceptions\CheckoutRefused;
use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\Address;
use App\Modules\Identity\Models\AdminUser;
use App\Modules\Identity\Models\Customer;
use App\Modules\Locations\Models\City;
use App\Modules\Locations\Models\Province;
use App\Modules\Notifications\Enums\SmsTemplate;
use App\Modules\Notifications\Jobs\SendSmsJob;
use App\Modules\Orders\Actions\SendOrderOnItsWay;
use App\Modules\Orders\Enums\CustomerOrderTab;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Enums\SourcingStatus;
use App\Modules\Orders\Exceptions\OrderRefused;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Enums\PaymentAttemptStatus;
use App\Modules\Payments\Enums\PaymentKind;
use App\Modules\Payments\Enums\RefundSource;
use App\Modules\Payments\Events\DifferencePaymentSucceeded;
use App\Modules\Payments\Filament\Resources\Payments\Pages\ListPayments;
use App\Modules\Payments\Filament\Resources\Refunds\Pages\ListRefunds;
use App\Modules\Payments\Filament\Resources\Refunds\Pages\ViewRefund;
use App\Modules\Payments\Models\Payment;
use App\Modules\Pricing\Enums\CommissionSource;
use App\Modules\Pricing\Enums\PriceChangeReason;
use App\Modules\Pricing\Enums\ProviderChoice;
use App\Modules\Pricing\Models\Provider;
use App\Modules\Pricing\Models\VariantPrice;
use App\Modules\Profile\Livewire\OrderDetail;
use App\Modules\Profile\Livewire\OrderList;
use App\Modules\Settings\Actions\SaveStoreSettings;
use App\Modules\Settings\Queries\StoreSettings;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Queue;

use function Pest\Livewire\livewire;

/*
| Staff edit a paid order before it goes out (owner, 2026-11-19): kept units at what was paid, new SKUs at today's price,
| shipping priced again; a higher total is owed through a one-hour link and holds the order back, a lower one opens a
| refund record for approval. «پرداخت‌ها» lists paid payments and refunds; the customer's orders have status tabs.
*/

beforeEach(function (): void {
    Queue::fake();
    Province::factory()->create([Province::COLUMN_ID => 8, Province::COLUMN_NAME_FA => 'تهران']);
    City::factory()->for(Province::query()->find(8))->deliveryEnabled()->create([City::COLUMN_ID => 1, City::COLUMN_NAME_FA => 'تهران']);
    app(SaveStoreSettings::class)([StoreSettings::SHIPPING_FLAT_RATE => 500_000, StoreSettings::FREE_SHIPPING_THRESHOLD => 50_000_000]);
    $this->customer = Customer::factory()->named()->create([Customer::COLUMN_MOBILE => '09123456789']);
    $this->address = Address::factory()->for($this->customer)->inCity(8, 1)->default()->create();
    $this->cream = editSku(1_000_000);
    $this->serum = editSku(2_000_000);
    $this->admin = AdminUser::factory()->withTwoFactorAuthentication()->create();
    // A paid order: 2 creams (1,000,000 each) and shipping 500,000 = 2,500,000 paid at the gateway.
    $placed = app(PlaceOrderForCustomer::class)(new PanelOrderData($this->customer->getId(), $this->address->getId(), [$this->cream->getId() => 2], 0, null, null, $this->admin->getId()));
    $this->order = Order::query()->findOrFail($placed->orderId);
    $this->order->setStatus(OrderStatus::Paid)->setPaymentStatus(PaymentStatus::Paid)->setPaidAt(now())->save();
    (new Payment)->setOrderId($this->order->getId())->setGateway('fake')->setAmount(2_500_000)->setAuthority('A-PAID')
        ->setUrl('https://gateway.test/A-PAID')->setStatus(PaymentAttemptStatus::Paid)->setExpiresAt(now())->setRefId('REF-PAID')->save();
});

function editSku(int $price): ProductVariant
{
    $variant = ProductVariant::factory()
        ->for(Product::factory()->active()->create())
        ->create([ProductVariant::COLUMN_OFFICIAL_PRICE => null, ProductVariant::COLUMN_PRICE => $price, ProductVariant::COLUMN_STOCK_STATUS => StockStatus::Available]);

    VariantPrice::factory()->create([
        VariantPrice::COLUMN_VARIANT_ID => $variant->getId(),
        VariantPrice::COLUMN_PROVIDER_ID => Provider::factory()->create()->getId(),
        VariantPrice::COLUMN_PROVIDER_COST => (int) ($price * 0.8),
        VariantPrice::COLUMN_PROVIDER_CHOICE => ProviderChoice::Cheapest,
        VariantPrice::COLUMN_COMMISSION_PERCENT => 25,
        VariantPrice::COLUMN_COMMISSION_SOURCE => CommissionSource::Brand,
        VariantPrice::COLUMN_PRICE => $price,
        VariantPrice::COLUMN_REASON => PriceChangeReason::Initial,
    ]);

    return $variant;
}

function editPaid(object $test, array $quantities): mixed
{
    return app(EditPaidOrder::class)(new PaidOrderEditData($test->order->getId(), $test->address->getId(), $quantities, $test->admin->getId()));
}

it('opens a refund record for approval when staff lower a paid order', function (): void {
    $edited = editPaid($this, [$this->cream->getId() => 1]);

    expect($edited)->total->toBe(1_500_000)->customerOwes->toBe(0)->refundOpened->toBe(1_000_000)
        ->and($this->order->refresh())->getTotal()->toBe(1_500_000)->getDifferenceDue()->toBe(0)
        ->and($this->order->items->sole())->getQuantity()->toBe(1)->getPaidTotal()->toBe(1_000_000);

    $refund = Payment::query()->where(Payment::COLUMN_KIND, PaymentKind::Refund)->sole();
    expect($refund)->getAmount()->toBe(1_000_000)->getRefundSource()->toBe(RefundSource::OrderEdit)->getStatus()->toBe(PaymentAttemptStatus::Pending);
});

it('owes the difference through a one-hour link when staff raise a paid order, and holds it back until paid', function (): void {
    $edited = editPaid($this, [$this->cream->getId() => 2, $this->serum->getId() => 1]);

    expect($edited)->total->toBe(4_500_000)->customerOwes->toBe(2_000_000)
        ->and($this->order->refresh())->getDifferenceDue()->toBe(2_000_000)->canPayDifference()->toBeTrue()
        ->and((int) round($this->order->getDifferenceDueUntil()->diffInMinutes(now(), true)))->toBe(60)
        ->and($this->order->items)->toHaveCount(2);
    Queue::assertPushed(SendSmsJob::class, fn (SendSmsJob $job): bool => $job->message->template === SmsTemplate::OrderPaymentLink);

    $this->order->setSourcingStatus(SourcingStatus::ReadyToPack)->save();
    expect(fn () => app(SendOrderOnItsWay::class)($this->order, 'CODE-1', 1))->toThrow(OrderRefused::class, 'تفاوت مبلغ');

    event(new DifferencePaymentSucceeded($this->order->getId(), 99, 2_000_000));
    expect($this->order->refresh())->getDifferenceDue()->toBe(0)->canPayDifference()->toBeFalse();

    $this->actingAs($this->customer, 'customer');
    livewire(OrderDetail::class, ['order' => $this->order->getId()])->assertDontSee('پرداخت تفاوت مبلغ');
});

it('shows the customer the difference to pay with a countdown', function (): void {
    editPaid($this, [$this->cream->getId() => 3]);
    $this->actingAs($this->customer, 'customer');

    livewire(OrderDetail::class, ['order' => $this->order->getId()])->assertSee('پرداخت تفاوت مبلغ')->assertSee('باید پرداخت کنید');
    livewire(OrderList::class)->assertSee('پرداخت تفاوت مبلغ');
});

it('refuses editing an order that went out, or raising a flash deal item', function (): void {
    $this->order->items->first()->setFlashDealId(null)->save();
    $this->order->setStatus(OrderStatus::OnTheWay)->save();

    expect(fn () => editPaid($this, [$this->cream->getId() => 1]))->toThrow(CheckoutRefused::class, 'قابل ویرایش نیست');
});

describe('in the admin', function (): void {
    beforeEach(function (): void {
        Filament::setCurrentPanel('admin');
        $this->seed(RolesSeeder::class);
    });

    it('lists only paid payments in «پرداخت‌ها» and refunds in «بازگشت وجه‌ها»; only super admins approve', function (): void {
        editPaid($this, [$this->cream->getId() => 1]);
        $refund = Payment::query()->where(Payment::COLUMN_KIND, PaymentKind::Refund)->sole();
        $failed = (new Payment)->setOrderId($this->order->getId())->setGateway('fake')->setAmount(100)->setAuthority('A-FAIL')
            ->setUrl('x')->setStatus(PaymentAttemptStatus::Failed)->setExpiresAt(now());
        $failed->save();
        $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::Operator)->create(), 'admin');

        livewire(ListPayments::class)->assertCanSeeTableRecords([Payment::query()->where(Payment::COLUMN_AUTHORITY, 'A-PAID')->sole()])
            ->assertCanNotSeeTableRecords([$failed, $refund]);
        livewire(ListRefunds::class)->assertCanSeeTableRecords([$refund]);
        livewire(ViewRefund::class, ['record' => $refund->getRouteKey()])
            ->assertSee($this->order->getNumber())
            ->assertSee('ویرایش سفارش')
            ->assertActionHidden('approve');

        $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::SuperAdmin)->create(), 'admin');
        livewire(ViewRefund::class, ['record' => $refund->getRouteKey()])
            ->callAction('approve', ['reference' => 'TR-1'])
            ->assertNotified('بازگشت وجه تأیید شد؛ به مشتری پیامک شد.');
        expect($refund->refresh())->getStatus()->toBe(PaymentAttemptStatus::Paid)->getRefId()->toBe('TR-1');
        Queue::assertPushed(SendSmsJob::class, fn (SendSmsJob $job): bool => $job->message->template === SmsTemplate::RefundPaid);
    });
});

it('gives the customer status tabs with counts, cancelled orders and returns', function (): void {
    $cancelled = Order::factory()->create([Order::COLUMN_CUSTOMER_ID => $this->customer->getId(), Order::COLUMN_STATUS => OrderStatus::Cancelled]);
    $this->actingAs($this->customer, 'customer');

    livewire(OrderList::class)
        ->assertSee('در حال آماده‌سازی')
        ->assertSee('مرجوعی')
        ->call('showTab', CustomerOrderTab::Cancelled->value)
        ->assertSet('tab', 'cancelled')
        ->assertSee($cancelled->getNumber())
        ->assertDontSee($this->order->getNumber())
        ->call('showTab', CustomerOrderTab::Processing->value)
        ->assertSee($this->order->getNumber());
});
