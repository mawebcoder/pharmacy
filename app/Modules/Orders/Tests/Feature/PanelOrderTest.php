<?php

declare(strict_types=1);

use App\Modules\Catalog\Enums\StockStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Checkout\Actions\PlaceOrderForCustomer;
use App\Modules\Checkout\Actions\ReviseUnpaidOrder;
use App\Modules\Checkout\Data\PanelOrderData;
use App\Modules\Checkout\Data\ReviseOrderData;
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
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Filament\Resources\Orders\OrderResource;
use App\Modules\Orders\Filament\Resources\Orders\Pages\CreatePanelOrder;
use App\Modules\Orders\Filament\Resources\Orders\Pages\ListOrders;
use App\Modules\Orders\Filament\Resources\Orders\Pages\ViewOrder;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Queries\OrderPaymentLink;
use App\Modules\Payments\Enums\PaymentAttemptStatus;
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
use Filament\Forms\Components\Repeater;
use Illuminate\Support\Facades\Queue;

use function Pest\Livewire\livewire;

/*
| Orders staff place in the panel (owner, 2026-11-19): an existing customer and saved address, SKUs at the site's
| prices, an optional discount with its reason, the admin who placed it, and a signed payment link texted to the
| customer; unpaid orders show their payment deadline counting down to the customer.
*/

beforeEach(function (): void {
    Queue::fake();
    Province::factory()->create([Province::COLUMN_ID => 8, Province::COLUMN_NAME_FA => 'تهران']);
    City::factory()->for(Province::query()->find(8))->deliveryEnabled()->create([City::COLUMN_ID => 1, City::COLUMN_NAME_FA => 'تهران']);
    app(SaveStoreSettings::class)([StoreSettings::SHIPPING_FLAT_RATE => 500_000, StoreSettings::FREE_SHIPPING_THRESHOLD => 50_000_000]);
    $this->customer = Customer::factory()->named()->create([Customer::COLUMN_MOBILE => '09123456789']);
    $this->address = Address::factory()->for($this->customer)->inCity(8, 1)->default()->create([Address::COLUMN_TITLE => 'خانه']);
    $this->cream = panelSku(1_000_000, 'کرم اوره');
    $this->serum = panelSku(2_000_000, 'سرم ویتامین سی');
    $this->admin = AdminUser::factory()->withTwoFactorAuthentication()->create();
});

function panelSku(int $price, string $name): ProductVariant
{
    $variant = ProductVariant::factory()
        ->for(Product::factory()->active()->create([Product::COLUMN_NAME_FA => $name]))
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

function placeForCustomer(object $test, array $quantities, int $discount = 0): Order
{
    $placed = app(PlaceOrderForCustomer::class)(new PanelOrderData(
        customerId: $test->customer->getId(),
        addressId: $test->address->getId(),
        quantities: $quantities,
        manualDiscount: $discount,
        manualDiscountReason: $discount > 0 ? 'مشتری قدیمی' : null,
        customerNote: 'تماس تلفنی',
        adminId: $test->admin->getId(),
    ));

    return Order::query()->findOrFail($placed->orderId);
}

it('places an order at the site\'s prices with the discount shared over the items, records the admin and texts the link', function (): void {
    $order = placeForCustomer($this, [$this->cream->getId() => 2, $this->serum->getId() => 1], 400_000);

    expect($order)
        ->getStatus()->toBe(OrderStatus::Pending)
        ->getSource()->toBe(OrderSource::Panel)
        ->getCreatedByAdminId()->toBe($this->admin->getId())
        ->getSubtotal()->toBe(4_000_000)
        ->getManualDiscount()->toBe(400_000)
        ->getManualDiscountReason()->toBe('مشتری قدیمی')
        ->getDiscountTotal()->toBe(400_000)
        ->getShippingTotal()->toBe(500_000)
        ->getTotal()->toBe(4_100_000)
        ->getCustomerNote()->toBe('تماس تلفنی')
        ->and((int) round($order->getExpiresAt()->diffInMinutes(now(), true)))->toBe(30)
        ->and($order->items->sum(fn (OrderItem $item): int => $item->getPaidTotal()))->toBe(3_600_000)
        ->and($order->items->sum(fn (OrderItem $item): int => $item->getCouponDiscountTotal()))->toBe(400_000);

    $link = app(OrderPaymentLink::class)->for($order);
    Queue::assertPushed(SendSmsJob::class, fn (SendSmsJob $job): bool => $job->message->template === SmsTemplate::OrderPaymentLink
        && $job->message->mobile === '09123456789'
        && $job->message->args[1] === $order->getNumber()
        && $job->message->args[2] === $link);
});

it('refuses a discount over the items, an address that is not the customer\'s, and no items', function (): void {
    expect(fn () => placeForCustomer($this, [$this->cream->getId() => 1], 1_500_000))->toThrow(CheckoutRefused::class, 'تخفیف از جمع کالاها')
        ->and(fn () => placeForCustomer($this, []))->toThrow(CheckoutRefused::class, 'دست‌کم یک کالا');

    $this->address = Address::factory()->for(Customer::factory()->create())->inCity(8, 1)->create();
    expect(fn () => placeForCustomer($this, [$this->cream->getId() => 1]))->toThrow(CheckoutRefused::class);
    expect(Order::query()->count())->toBe(0);
});

it('opens the gateway from the signed link without signing in, and refuses a tampered one', function (): void {
    $order = placeForCustomer($this, [$this->cream->getId() => 1]);
    $link = app(OrderPaymentLink::class)->for($order);

    $this->get($link)->assertRedirect();
    $this->get(route('orders.pay', $order->getId()))->assertForbidden();

    $order->setStatus(OrderStatus::Expired)->save();
    expect(app(OrderPaymentLink::class)->for($order))->toBeNull();
    $this->get($link)->assertRedirect(route('account.orders.show', $order->getId()));
});

it('shows the customer the payment deadline counting down on the order list and page, and the rules after it ships', function (): void {
    $order = placeForCustomer($this, [$this->cream->getId() => 1]);
    $this->actingAs($this->customer, 'customer');

    livewire(OrderList::class)->assertSee('سفارش در انتظار پرداخت دارید')->assertSee('تا پایان مهلت پرداخت');
    livewire(OrderDetail::class, ['order' => $order->getId()])->assertSee('تا پایان مهلت پرداخت')->assertSee('سفارش لغو می‌شود');

    $order->setStatus(OrderStatus::OnTheWay)->save();
    livewire(OrderDetail::class, ['order' => $order->getId()])
        ->assertDontSee('تا پایان مهلت پرداخت')
        ->assertSee('دیگر قابل ویرایش یا لغو نیست');
});

it('lets the customer change quantities, remove items and the address of an unpaid order, closing the old link', function (): void {
    $order = placeForCustomer($this, [$this->cream->getId() => 2, $this->serum->getId() => 1], 300_000);
    $old = (new Payment)->setOrderId($order->getId())->setGateway('fake')->setAmount($order->getTotal())->setAuthority('A-OLD')
        ->setUrl('https://gateway.test/A-OLD')->setStatus(PaymentAttemptStatus::Pending)->setExpiresAt(now()->addMinutes(20));
    $old->save();
    $work = Address::factory()->for($this->customer)->inCity(8, 1)->create([Address::COLUMN_TITLE => 'محل کار']);
    $this->actingAs($this->customer, 'customer');

    livewire(OrderDetail::class, ['order' => $order->getId()])
        ->call('startEdit')
        ->assertSet('editing', true)
        ->set('editQuantities.'.$this->cream->getId(), 1)
        ->set('editQuantities.'.$this->serum->getId(), 0)
        ->set('editAddressId', $work->getId())
        ->call('saveEdit')
        ->assertSet('editing', false);

    expect($order->refresh())
        ->getSubtotal()->toBe(1_000_000)
        ->getManualDiscount()->toBe(300_000) // staff's discount stays
        ->getTotal()->toBe(1_000_000 - 300_000 + 500_000)
        ->getAddressId()->toBe($work->getId())
        ->and($order->items)->toHaveCount(1)
        ->and($old->refresh()->getStatus())->toBe(PaymentAttemptStatus::Failed);
});

it('refuses editing someone else\'s order, a paid one, or one past its deadline', function (): void {
    $order = placeForCustomer($this, [$this->cream->getId() => 1]);
    $edit = fn (?int $customerId) => app(ReviseUnpaidOrder::class)(new ReviseOrderData($order->getId(), $this->address->getId(), [$this->cream->getId() => 2], customerId: $customerId));

    expect(fn () => $edit($this->customer->getId() + 1))->toThrow(CheckoutRefused::class, 'قابل ویرایش نیست');

    $this->travel(31)->minutes();
    expect(fn () => $edit($this->customer->getId()))->toThrow(CheckoutRefused::class, 'قابل ویرایش نیست');
});

describe('in the admin', function (): void {
    beforeEach(function (): void {
        Filament::setCurrentPanel('admin');
        $this->seed(RolesSeeder::class);
        $this->operator = AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::Operator)->create();
        $this->actingAs($this->operator, 'admin');
    });

    it('offers «ثبت سفارش» at the top of the order list and places the order from the form', function (): void {
        Repeater::fake();

        livewire(ListOrders::class)->assertActionVisible('create');

        livewire(CreatePanelOrder::class)
            ->set('data.customer_id', $this->customer->getId())
            ->set('data.address_id', $this->address->getId())
            ->set('data.items', [['variant_id' => $this->cream->getId(), 'quantity' => 2]])
            ->set('data.manual_discount', 10_000)
            ->set('data.manual_discount_reason', 'هماهنگی تلفنی')
            ->call('create')
            ->assertHasNoFormErrors();

        $order = Order::query()->sole();
        expect($order)->getSource()->toBe(OrderSource::Panel)->getCreatedByAdminId()->toBe($this->operator->getId())->getManualDiscount()->toBe(100_000);
    });

    it('lets staff edit an unpaid panel order and texts the new link', function (): void {
        Repeater::fake();
        $order = placeForCustomer($this, [$this->cream->getId() => 1]);

        livewire(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('editUnpaid', [
                'address_id' => $this->address->getId(),
                'items' => [['variant_id' => $this->cream->getId(), 'quantity' => 1], ['variant_id' => $this->serum->getId(), 'quantity' => 2]],
                'manual_discount' => 50_000,
                'manual_discount_reason' => 'جبران تأخیر',
            ])
            ->assertHasNoActionErrors();

        expect($order->refresh())->getSubtotal()->toBe(5_000_000)->getManualDiscount()->toBe(500_000)->getTotal()->toBe(5_000_000)
            ->and($order->items)->toHaveCount(2);
        Queue::assertPushed(SendSmsJob::class, 2); // placed, then edited
    });

    it('shows who placed it and its payment link, and texts the link again', function (): void {
        $order = placeForCustomer($this, [$this->cream->getId() => 1]);

        livewire(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertSee('پنل مدیریت')
            ->assertSee('لینک پرداخت')
            ->callAction('resendPaymentLink')
            ->assertNotified('لینک پرداخت برای مشتری پیامک شد.');

        Queue::assertPushed(SendSmsJob::class, 2);
        expect(OrderResource::canCreate())->toBeTrue();
    });
});
