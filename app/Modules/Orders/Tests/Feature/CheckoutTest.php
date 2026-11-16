<?php

declare(strict_types=1);

use App\Modules\Cart\Actions\AddToCart;
use App\Modules\Cart\Actions\SettleOrderCoupon;
use App\Modules\Cart\Data\CartOwnerData;
use App\Modules\Cart\Enums\CouponUsageStatus;
use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Models\CartItem;
use App\Modules\Cart\Models\Coupon;
use App\Modules\Cart\Models\CouponUsage;
use App\Modules\Catalog\Actions\SyncFlashDealPrices;
use App\Modules\Catalog\Enums\StockStatus;
use App\Modules\Catalog\Models\FlashDeal;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Checkout\Livewire\AddressStep;
use App\Modules\Checkout\Livewire\CheckoutPage;
use App\Modules\Identity\Models\Address;
use App\Modules\Identity\Models\Customer;
use App\Modules\Locations\Models\City;
use App\Modules\Locations\Models\Province;
use App\Modules\Orders\Actions\ChangeOrderStatus;
use App\Modules\Orders\Actions\PlaceOrder;
use App\Modules\Orders\Data\PlacedOrderData;
use App\Modules\Orders\Data\PlaceOrderData;
use App\Modules\Orders\Enums\OrderActor;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Exceptions\CheckoutRefused;
use App\Modules\Orders\Jobs\ExpirePendingOrders;
use App\Modules\Orders\Livewire\Account\OrderDetail;
use App\Modules\Orders\Livewire\Account\OrderList;
use App\Modules\Orders\Livewire\PaymentResult;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Models\OrderStatusLog;
use App\Modules\Payments\Actions\HandlePaymentCallback;
use App\Modules\Payments\Enums\PaymentAttemptStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Pricing\Enums\CommissionSource;
use App\Modules\Pricing\Enums\PriceChangeReason;
use App\Modules\Pricing\Enums\ProviderChoice;
use App\Modules\Pricing\Models\Provider;
use App\Modules\Pricing\Models\ProviderPrice;
use App\Modules\Pricing\Models\VariantPrice;
use App\Modules\Settings\Actions\SaveStoreSettings;
use App\Modules\Settings\Queries\StoreSettings;
use Illuminate\Database\UniqueConstraintViolationException;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->province = Province::factory()->create([Province::COLUMN_ID => 8, Province::COLUMN_NAME_FA => 'تهران']);
    $this->city = City::factory()->for($this->province)->deliveryEnabled()->create([City::COLUMN_ID => 1, City::COLUMN_NAME_FA => 'تهران']);
    $this->customer = Customer::factory()->named()->create([Customer::COLUMN_MOBILE => '09123456789']);
    app(SaveStoreSettings::class)([StoreSettings::SHIPPING_FLAT_RATE => 500_000, StoreSettings::FREE_SHIPPING_THRESHOLD => 50_000_000]);
});

/**
 * An in-stock SKU at a price (rial) with a price record from a supplier.
 */
function checkoutSku(int $price, string $name = 'کرم اوره'): ProductVariant
{
    $variant = ProductVariant::factory()
        ->for(Product::factory()->active()->create([Product::COLUMN_NAME_FA => $name]))
        ->create([ProductVariant::COLUMN_OFFICIAL_PRICE => null, ProductVariant::COLUMN_PRICE => $price, ProductVariant::COLUMN_STOCK_STATUS => StockStatus::Available]);

    VariantPrice::factory()->create([
        VariantPrice::COLUMN_VARIANT_ID => $variant->getId(),
        VariantPrice::COLUMN_PROVIDER_ID => Provider::factory()->create([Provider::COLUMN_NAME => 'پخش البرز'])->getId(),
        VariantPrice::COLUMN_PROVIDER_COST => (int) ($price * 0.8),
        VariantPrice::COLUMN_PROVIDER_CHOICE => ProviderChoice::Cheapest,
        VariantPrice::COLUMN_COMMISSION_PERCENT => 25,
        VariantPrice::COLUMN_COMMISSION_SOURCE => CommissionSource::Brand,
        VariantPrice::COLUMN_PRICE => $price,
        VariantPrice::COLUMN_REASON => PriceChangeReason::Initial,
    ]);

    return $variant;
}

/**
 * @param  array<int, int>  $quantities  variant id => quantity
 */
function fillCart(Customer $customer, array $quantities): void
{
    foreach ($quantities as $variantId => $quantity) {
        expect(app(AddToCart::class)(new CartOwnerData($customer->getId(), null), $variantId, $quantity))->toBe($quantity);
    }
}

function homeAddress(Customer $customer, bool $default = true): Address
{
    $factory = Address::factory()->for($customer)->inCity(8, 1);

    return ($default ? $factory->default() : $factory)->create([Address::COLUMN_TITLE => $default ? 'خانه' : 'محل کار', Address::COLUMN_PLAQUE => '12', Address::COLUMN_POSTAL_CODE => '1234567890']);
}

function placeOrder(Customer $customer, int $shownTotal, ?string $coupon = null): PlacedOrderData
{
    return app(PlaceOrder::class)(new PlaceOrderData($customer->getId(), $coupon, $shownTotal));
}

describe('the address step', function (): void {
    it('sends an empty cart back to the cart', function (): void {
        $this->actingAs($this->customer, 'customer');

        livewire(AddressStep::class)->assertRedirect(route('cart'));
    });

    it('keeps the picked address on the cart and goes on to checkout, ignoring others\' addresses', function (): void {
        $this->actingAs($this->customer, 'customer');
        fillCart($this->customer, [checkoutSku(1_000_000)->getId() => 1]);
        homeAddress($this->customer);
        $work = homeAddress($this->customer, default: false);
        $theirs = homeAddress(Customer::factory()->create());

        livewire(AddressStep::class)->call('picked', $theirs->getId());
        expect(Cart::query()->sole()->getAddressId())->toBeNull();

        livewire(AddressStep::class)
            ->call('picked', $work->getId())
            ->assertRedirect(route('checkout'));
        expect(Cart::query()->sole()->getAddressId())->toBe($work->getId());
    });
});

describe('the checkout page', function (): void {
    it('asks for an address first when the customer has none', function (): void {
        $this->actingAs($this->customer, 'customer');
        fillCart($this->customer, [checkoutSku(1_000_000)->getId() => 1]);

        livewire(CheckoutPage::class)->assertRedirect(route('checkout.address'));
        livewire(AddressStep::class)
            ->assertSeeHtml('wire:click="proceed" disabled')
            ->call('proceed')
            ->assertHasErrors('address')
            ->assertNoRedirect();
    });

    it('uses the picked address, else the default when none was picked or the picked one is gone', function (): void {
        $this->actingAs($this->customer, 'customer');
        fillCart($this->customer, [checkoutSku(1_000_000)->getId() => 1]);
        homeAddress($this->customer);
        $work = homeAddress($this->customer, default: false);

        livewire(CheckoutPage::class)->assertSee('خانه')->assertDontSee('محل کار');

        Cart::query()->sole()->setAddressId($work->getId())->save();
        livewire(CheckoutPage::class)->assertSee('محل کار');

        $work->delete();
        livewire(CheckoutPage::class)->assertSee('خانه');
    });

    it('charges the flat shipping rate, free over the threshold', function (): void {
        $this->actingAs($this->customer, 'customer');
        $variant = checkoutSku(1_000_000);
        fillCart($this->customer, [$variant->getId() => 1]);
        homeAddress($this->customer);

        livewire(CheckoutPage::class)->assertSet('shownTotal', 1_500_000);

        app(SaveStoreSettings::class)([StoreSettings::FREE_SHIPPING_THRESHOLD => 1_000_000]);
        livewire(CheckoutPage::class)->assertSet('shownTotal', 1_000_000)->assertSee('رایگان');
    });

    it('names the items that can no longer be bought and holds the payment button', function (): void {
        $this->actingAs($this->customer, 'customer');
        $variant = checkoutSku(1_000_000, 'شربت تمام‌شده');
        fillCart($this->customer, [$variant->getId() => 1]);
        homeAddress($this->customer);
        $variant->setStockStatus(StockStatus::OutOfStock)->save();

        livewire(CheckoutPage::class)->assertSee(['این کالاها دیگر قابل خرید نیستند:', 'شربت تمام‌شده', 'ابتدا سبد خرید را اصلاح کنید']);
    });

    it('only logs an applied coupon and works the discount out on the fly', function (): void {
        $this->actingAs($this->customer, 'customer');
        fillCart($this->customer, [checkoutSku(2_000_000)->getId() => 1]);
        homeAddress($this->customer);
        $coupon = Coupon::factory()->percent(10)->create([Coupon::COLUMN_CODE => 'TEN']);

        livewire(CheckoutPage::class)
            ->set('couponInput', 'ten')
            ->call('applyCoupon')
            ->assertHasNoErrors()
            ->assertSet('shownTotal', 2_000_000 - 200_000 + 500_000);

        expect(CouponUsage::query()->sole()->getStatus())->toBe(CouponUsageStatus::Checkout)
            ->and(CouponUsage::query()->sole()->getCouponId())->toBe($coupon->getId())
            ->and(Order::query()->count())->toBe(0);
    });

    it('sends the customer to the gateway without a terms checkbox', function (): void {
        $this->actingAs($this->customer, 'customer');
        fillCart($this->customer, [checkoutSku(1_000_000)->getId() => 1]);
        homeAddress($this->customer);

        livewire(CheckoutPage::class)
            ->assertDontSeeHtml('type="checkbox"')
            ->call('pay')
            ->assertHasNoErrors()
            ->assertRedirect(Payment::query()->first()?->getUrl() ?? 'missing');
        expect(Order::query()->count())->toBe(1);
    });
});

describe('placing the order', function (): void {
    it('creates a pending order with copies of the address, customer, items, supplier and coupon', function (): void {
        // The expiry is compared to the second; a clock tick between placing and checking made this flaky.
        $this->freezeSecond();
        $cream = checkoutSku(1_000_000, 'کرم اوره');
        $serum = checkoutSku(3_000_005, 'سرم ویتامین C');
        fillCart($this->customer, [$cream->getId() => 2, $serum->getId() => 1]);
        $address = homeAddress($this->customer);
        Coupon::factory()->percent(10)->create([Coupon::COLUMN_CODE => 'TEN']);

        $placed = placeOrder($this->customer, 2_000_000 + 3_000_005 - 500_000 + 500_000, 'TEN');

        $order = Order::query()->with('items')->findOrFail($placed->orderId);
        $item = $order->items->first(fn (OrderItem $item): bool => $item->getVariantId() === $cream->getId());
        expect($order->getStatus())->toBe(OrderStatus::Pending)
            ->and($order->getPaymentStatus())->toBe(PaymentStatus::Pending)
            ->and($order->getNumber())->toMatch('/^PH-\d{6}-\d{6}$/')
            ->and($order->getSubtotal())->toBe(5_000_005)
            ->and($order->getDiscountTotal())->toBe(500_000)
            ->and($order->getShippingTotal())->toBe(500_000)
            ->and($order->getTotal())->toBe(5_000_005)
            ->and($order->getExpiresAt()?->timestamp)->toBe(now()->addMinutes((int) config('payments.pending_expiry_minutes'))->timestamp)
            ->and($order->getAddressSnapshot())->toMatchArray(['address_id' => $address->getId(), 'province' => 'تهران', 'city' => 'تهران', 'main_street' => 'ولیعصر', 'plaque' => '12', 'postal_code' => '1234567890'])
            ->and($order->getCustomerSnapshot())->toBe(['name' => 'سارا رضایی', 'mobile' => '09123456789'])
            ->and($order->getCouponCode())->toBe('TEN')
            ->and($order->getCouponSnapshot())->toMatchArray(['type' => 'percent', 'value' => 10])
            ->and($item->getProductName())->toBe('کرم اوره')
            ->and($item->getQuantity())->toBe(2)
            ->and($item->getUnitPrice())->toBe(1_000_000)
            ->and($item->getProviderName())->toBe('پخش البرز')
            ->and($item->getUnitCost())->toBe(800_000)
            ->and($item->getCommissionPercent())->toBe(25.0)
            ->and($item->getCommissionSource())->toBe('brand')
            ->and($item->getProviderChoice())->toBe('cheapest')
            ->and($item->getVariantPriceId())->not->toBeNull()
            ->and($item->getProductSnapshot()['product']['name_fa'] ?? null)->toBe('کرم اوره')
            ->and($item->getProductSnapshot()['sku']['sku'] ?? null)->toBe($cream->getSku());
    });

    it('shares the coupon discount over the items so their discounts add up to the order\'s', function (): void {
        $variants = [checkoutSku(1_000_003), checkoutSku(2_000_007), checkoutSku(3_333_333)];
        fillCart($this->customer, [$variants[0]->getId() => 1, $variants[1]->getId() => 3, $variants[2]->getId() => 1]);
        homeAddress($this->customer);
        Coupon::factory()->percent(7)->create([Coupon::COLUMN_CODE => 'SEVEN']);
        $subtotal = 1_000_003 + 3 * 2_000_007 + 3_333_333;
        $discount = intdiv(intdiv($subtotal * 7, 100), 10) * 10;

        $order = Order::query()->with('items')->findOrFail(placeOrder($this->customer, $subtotal - $discount + 500_000, 'SEVEN')->orderId);

        expect($order->getDiscountTotal())->toBe($discount)
            ->and($order->items->sum(fn (OrderItem $item): int => $item->getCouponDiscountTotal()))->toBe($discount)
            ->and($order->items->every(fn (OrderItem $item): bool => $item->getPaidTotal() === $item->getUnitPrice() * $item->getQuantity() - $item->getCouponDiscountTotal()))->toBeTrue();
    });

    it('keeps each item\'s haraj, coupon and paid amounts per unit and in total, adding up to the order\'s', function (): void {
        $onHaraj = checkoutSku(1_000_000);
        VariantPrice::query()->where(VariantPrice::COLUMN_VARIANT_ID, $onHaraj->getId())->update([VariantPrice::COLUMN_PRICE => 1_250_000, VariantPrice::COLUMN_SALE_PRICE => 1_000_000]);
        $variants = [$onHaraj, checkoutSku(2_000_007), checkoutSku(3_333_333)];
        fillCart($this->customer, [$variants[0]->getId() => 3, $variants[1]->getId() => 7, $variants[2]->getId() => 1]);
        homeAddress($this->customer);
        Coupon::factory()->percent(7)->create([Coupon::COLUMN_CODE => 'SEVEN']);
        $subtotal = 3 * 1_000_000 + 7 * 2_000_007 + 3_333_333;
        $discount = intdiv(intdiv($subtotal * 7, 100), 10) * 10;

        $order = Order::query()->with('items')->findOrFail(placeOrder($this->customer, $subtotal - $discount + 500_000, 'SEVEN')->orderId);
        $haraj = $order->items->first(fn (OrderItem $item): bool => $item->getVariantId() === $onHaraj->getId());

        expect($order->items->sum(fn (OrderItem $item): int => $item->getUnitPrice() * $item->getQuantity()))->toBe($order->getSubtotal())
            ->and($order->items->sum(fn (OrderItem $item): int => $item->getCouponDiscountTotal()))->toBe($order->getDiscountTotal())
            ->and($order->items->sum(fn (OrderItem $item): int => $item->getPaidTotal()))->toBe($order->getSubtotal() - $order->getDiscountTotal())
            ->and($order->items->sum(fn (OrderItem $item): int => $item->getPaidTotal()) + $order->getShippingTotal())->toBe($order->getTotal())
            ->and($haraj->getHarajDiscountUnit())->toBe(250_000)
            ->and($haraj->getHarajDiscountTotal())->toBe(750_000);

        foreach ($order->items as $item) {
            $quantity = $item->getQuantity();

            expect($item->getHarajDiscountTotal())->toBe($item->getHarajDiscountUnit() * $quantity)
                ->and($item->getListPrice() - $item->getHarajDiscountUnit())->toBe($item->getUnitPrice())
                ->and($item->getPaidUnit())->toBe(intdiv($item->getPaidTotal(), $quantity))
                ->and($item->getCouponDiscountUnit())->toBe($item->getUnitPrice() - $item->getPaidUnit())
                ->and($item->getPaidTotal())->toBe($item->getUnitPrice() * $quantity - $item->getCouponDiscountTotal())
                // Paid rounded down per unit: returns never refund more; the last ones take the remainder (under quantity rial).
                ->and($item->getPaidTotal() - $item->getPaidUnit() * $quantity)->toBeGreaterThanOrEqual(0)->toBeLessThan($quantity);
        }
    });

    it('records the shipping price, its discount and why on every order', function (int $price, ?string $code, int $charged, int $discount, ?string $reason): void {
        fillCart($this->customer, [checkoutSku($price)->getId() => 1]);
        homeAddress($this->customer);
        Coupon::factory()->freeShipping()->create([Coupon::COLUMN_CODE => 'SHIP']);

        $order = Order::query()->findOrFail(placeOrder($this->customer, $price + $charged, $code)->orderId);

        expect($order)
            ->getShippingPrice()->toBe(500_000)
            ->getShippingDiscount()->toBe($discount)
            ->getShippingDiscountReason()->toBe($reason)
            ->getShippingTotal()->toBe($charged)
            ->and($order->getShippingPrice() - $order->getShippingDiscount())->toBe($order->getShippingTotal());
    })->with([
        'paid shipping' => [1_000_000, null, 500_000, 0, null],
        'free-shipping coupon' => [1_000_000, 'SHIP', 0, 500_000, 'coupon'],
        'free-shipping threshold' => [50_000_000, null, 0, 500_000, 'threshold'],
    ]);

    it('reserves the coupon, empties the cart, logs the status and opens one payment link', function (): void {
        fillCart($this->customer, [checkoutSku(1_000_000)->getId() => 1]);
        homeAddress($this->customer);
        Coupon::factory()->freeShipping()->create([Coupon::COLUMN_CODE => 'SHIP']);

        $placed = placeOrder($this->customer, 1_000_000, 'SHIP');

        expect(CouponUsage::query()->sole())->getStatus()->toBe(CouponUsageStatus::Reserved)->getOrderId()->toBe($placed->orderId)
            ->and(CartItem::query()->count())->toBe(0)
            ->and(OrderStatusLog::query()->sole())->getToStatus()->toBe(OrderStatus::Pending)->getActorType()->toBe(OrderActor::Customer)
            ->and(Payment::query()->sole())->getStatus()->toBe(PaymentAttemptStatus::Pending)->getAmount()->toBe(1_000_000)
            ->and($placed->paymentUrl)->toBe(Payment::query()->sole()->getUrl());
    });

    it('creates nothing and says why when something changed since the page was shown', function (string $change, string $code): void {
        $variant = checkoutSku(1_000_000);
        fillCart($this->customer, [$variant->getId() => 1]);
        homeAddress($this->customer);
        $shown = 1_500_000;
        $coupon = null;

        match ($change) {
            'price' => $variant->setPrice(1_200_000)->save(),
            'stock' => $variant->setStockStatus(StockStatus::OutOfStock)->save(),
            'coupon' => $coupon = Coupon::factory()->inactive()->create([Coupon::COLUMN_CODE => 'OFF'])->getCode(),
            'city' => $this->city->setIsDeliveryEnabled(false)->save(),
            default => $shown = 999,
        };

        expect(fn (): PlacedOrderData => placeOrder($this->customer, $shown, $coupon))
            ->toThrow(fn (CheckoutRefused $refused): bool => $refused->errorCode() === $code);

        expect(Order::query()->count())->toBe(0)
            ->and(CartItem::query()->count())->toBe(1);
    })->with([
        'price changed' => ['price', 'checkout_total_changed'],
        'out of stock' => ['stock', 'checkout_items_unavailable'],
        'coupon no longer valid' => ['coupon', 'checkout_coupon_invalid'],
        'city delivery off' => ['city', 'checkout_address_not_deliverable'],
        'tampered total' => ['tampered', 'checkout_total_changed'],
    ]);

    it('keeps the order unchanged when the product, price, address or city change later', function (): void {
        $variant = checkoutSku(1_000_000, 'کرم اوره');
        fillCart($this->customer, [$variant->getId() => 1]);
        $address = homeAddress($this->customer);
        $order = Order::query()->with('items')->findOrFail(placeOrder($this->customer, 1_500_000)->orderId);
        $before = [$order->getAddressSnapshot(), $order->items->first()?->toArray()];

        $variant->product?->setNameFa('کرم جدید')->save();
        $variant->setPrice(9_000_000)->save();
        $address->setMainStreet('انقلاب')->setPostalCode('9999999999')->save();
        $this->city->setNameFa('شهر دیگر')->save();

        $after = Order::query()->with('items')->findOrFail($order->getId());
        expect([$after->getAddressSnapshot(), $after->items->first()?->toArray()])->toBe($before);
    });
});

describe('paying', function (): void {
    it('marks the order paid and the coupon used when the gateway confirms', function (): void {
        fillCart($this->customer, [checkoutSku(1_000_000)->getId() => 1]);
        homeAddress($this->customer);
        Coupon::factory()->freeShipping()->create([Coupon::COLUMN_CODE => 'SHIP']);
        $placed = placeOrder($this->customer, 1_000_000, 'SHIP');
        $authority = Payment::query()->sole()->getAuthority();

        $this->get(route('payments.callback', ['Authority' => $authority, 'Status' => 'OK']))->assertRedirect(route('checkout.result', $placed->orderId));
        $this->get(route('payments.callback', ['Authority' => $authority, 'Status' => 'OK']))->assertRedirect();

        $order = Order::query()->findOrFail($placed->orderId);
        expect($order->getStatus())->toBe(OrderStatus::Paid)
            ->and($order->getPaymentStatus())->toBe(PaymentStatus::Paid)
            ->and($order->getPaidAt())->not->toBeNull()
            ->and(CouponUsage::query()->sole()->getStatus())->toBe(CouponUsageStatus::Used)
            ->and(OrderStatusLog::query()->where(OrderStatusLog::COLUMN_TO_STATUS, OrderStatus::Paid)->count())->toBe(1);
    });

    it('keeps one link per order: reused while open, a new one only after a failure', function (): void {
        $this->actingAs($this->customer, 'customer');
        fillCart($this->customer, [checkoutSku(1_000_000)->getId() => 1]);
        homeAddress($this->customer);
        $placed = placeOrder($this->customer, 1_500_000);
        $first = Payment::query()->sole();

        livewire(OrderList::class)->call('pay', $placed->orderId)->assertRedirect($first->getUrl());
        expect(Payment::query()->count())->toBe(1);

        $this->get(route('payments.callback', ['Authority' => $first->getAuthority(), 'Status' => 'NOK']));
        expect($first->refresh()->getStatus())->toBe(PaymentAttemptStatus::Failed)
            ->and(Order::query()->findOrFail($placed->orderId)->getStatus())->toBe(OrderStatus::Pending);

        livewire(OrderList::class)->call('pay', $placed->orderId);
        expect(Payment::query()->count())->toBe(2)
            ->and(Payment::query()->open()->count())->toBe(1);
    });

    it('refuses a second successful payment for an order that is already paid', function (): void {
        fillCart($this->customer, [checkoutSku(1_000_000)->getId() => 1]);
        homeAddress($this->customer);
        $placed = placeOrder($this->customer, 1_500_000);
        $first = Payment::query()->sole();
        // A second attempt still open, as from another tab.
        $second = $first->replicate([Payment::COLUMN_PAID_ORDER_ID])->setAuthority('SECOND-TAB')->setStatus(PaymentAttemptStatus::Pending);
        $second->save();

        $this->get(route('payments.callback', ['Authority' => $first->getAuthority(), 'Status' => 'OK']));
        $this->get(route('payments.callback', ['Authority' => 'SECOND-TAB', 'Status' => 'OK']));

        expect($second->refresh())
            ->getStatus()->toBe(PaymentAttemptStatus::Failed)
            ->getFailureReason()->toBe(HandlePaymentCallback::ALREADY_PAID)
            ->getRefId()->toBeNull()
            ->and(Payment::query()->where(Payment::COLUMN_STATUS, PaymentAttemptStatus::Paid)->count())->toBe(1)
            ->and(Order::query()->findOrFail($placed->orderId)->getStatus())->toBe(OrderStatus::Paid)
            ->and(OrderStatusLog::query()->where(OrderStatusLog::COLUMN_TO_STATUS, OrderStatus::Paid)->count())->toBe(1);
    });

    it('lets the database keep a single paid payment per order', function (): void {
        fillCart($this->customer, [checkoutSku(1_000_000)->getId() => 1]);
        homeAddress($this->customer);
        placeOrder($this->customer, 1_500_000);
        $first = Payment::query()->sole();
        $first->setStatus(PaymentAttemptStatus::Paid)->save();

        expect(fn () => $first->replicate([Payment::COLUMN_PAID_ORDER_ID])->setAuthority('SECOND-TAB')->setStatus(PaymentAttemptStatus::Paid)->save())
            ->toThrow(UniqueConstraintViolationException::class);
    });

    it('counts flash deal units when the order is placed, never past the limit, and gives them back when it expires', function (): void {
        $variant = checkoutSku(1_000_000);
        $deal = FlashDeal::factory()->limitedTo(2)->create([FlashDeal::COLUMN_VARIANT_ID => $variant->getId(), FlashDeal::COLUMN_DEAL_PRICE => 800_000]);
        app(SyncFlashDealPrices::class)();
        fillCart($this->customer, [$variant->getId() => 2]);
        homeAddress($this->customer);

        $placed = placeOrder($this->customer, 2 * 800_000 + 500_000);

        expect($deal->fresh()->getSoldCount())->toBe(2)
            ->and(OrderItem::query()->sole()->getFlashDealId())->toBe($deal->getId())
            ->and(OrderItem::query()->sole()->getUnitPrice())->toBe(800_000)
            // Sold out: the SKU is back at its regular price.
            ->and($variant->fresh()->currentPrice())->toBe(1_000_000);

        $this->travel((int) config('payments.pending_expiry_minutes') + 1)->minutes();
        app(ExpirePendingOrders::class)->handle(app(ChangeOrderStatus::class), app(SettleOrderCoupon::class));

        expect(Order::query()->findOrFail($placed->orderId)->getStatus())->toBe(OrderStatus::Expired)
            ->and($deal->fresh()->getSoldCount())->toBe(0)
            ->and($variant->fresh()->currentPrice())->toBe(800_000);
    });

    it('records the deal\'s supplier and its cost on items bought under a flash deal', function (): void {
        $variant = checkoutSku(1_000_000);
        $sina = Provider::factory()->create([Provider::COLUMN_NAME => 'پخش سینا']);
        ProviderPrice::factory()->create([ProviderPrice::COLUMN_PROVIDER_ID => $sina->getId(), ProviderPrice::COLUMN_VARIANT_ID => $variant->getId(), ProviderPrice::COLUMN_COST => 600_000]);
        FlashDeal::factory()->create([FlashDeal::COLUMN_VARIANT_ID => $variant->getId(), FlashDeal::COLUMN_PROVIDER_ID => $sina->getId(), FlashDeal::COLUMN_DEAL_PRICE => 800_000]);
        app(SyncFlashDealPrices::class)();
        fillCart($this->customer, [$variant->getId() => 1]);
        homeAddress($this->customer);

        placeOrder($this->customer, 800_000 + 500_000);

        expect(OrderItem::query()->sole())
            ->getProviderId()->toBe($sina->getId())
            ->getProviderName()->toBe('پخش سینا')
            ->getUnitCost()->toBe(600_000)
            ->getUnitPrice()->toBe(800_000);
    });

    it('refuses the order when the deal sold out since the page was shown', function (): void {
        $variant = checkoutSku(1_000_000);
        $deal = FlashDeal::factory()->limitedTo(1)->create([FlashDeal::COLUMN_VARIANT_ID => $variant->getId(), FlashDeal::COLUMN_DEAL_PRICE => 800_000]);
        app(SyncFlashDealPrices::class)();
        fillCart($this->customer, [$variant->getId() => 1]);
        homeAddress($this->customer);
        // Someone else took the last unit a moment ago; the SKU still shows the deal price to this customer.
        $deal->setSoldCount(1)->save();

        expect(fn () => placeOrder($this->customer, 800_000 + 500_000))->toThrow(CheckoutRefused::class, 'پیشنهاد لحظه‌ای')
            ->and(Order::query()->count())->toBe(0)
            ->and($deal->fresh()->getSoldCount())->toBe(1);
    });

    it('expires an unpaid order, releases its coupon and refuses to pay it', function (): void {
        $this->actingAs($this->customer, 'customer');
        fillCart($this->customer, [checkoutSku(1_000_000)->getId() => 1]);
        homeAddress($this->customer);
        Coupon::factory()->freeShipping()->create([Coupon::COLUMN_CODE => 'SHIP']);
        $placed = placeOrder($this->customer, 1_000_000, 'SHIP');

        $this->travel((int) config('payments.pending_expiry_minutes') + 1)->minutes();
        app(ExpirePendingOrders::class)->handle(app(ChangeOrderStatus::class), app(SettleOrderCoupon::class));

        $order = Order::query()->findOrFail($placed->orderId);
        expect($order->getStatus())->toBe(OrderStatus::Expired)
            ->and($order->getPaymentStatus())->toBe(PaymentStatus::Expired)
            ->and(CouponUsage::query()->sole()->getStatus())->toBe(CouponUsageStatus::Released);

        livewire(OrderList::class)->call('pay', $placed->orderId)->assertNoRedirect();
        expect(Payment::query()->count())->toBe(1);
    });

    it('leaves the order payable later when the gateway is unavailable', function (): void {
        config(['payments.fake_enabled' => false]);
        fillCart($this->customer, [checkoutSku(1_000_000)->getId() => 1]);
        homeAddress($this->customer);

        $placed = placeOrder($this->customer, 1_500_000);

        expect($placed->paymentUrl)->toBeNull()
            ->and(Order::query()->findOrFail($placed->orderId)->canBePaid())->toBeTrue()
            ->and(Payment::query()->count())->toBe(0);
    });
});

describe('the customer\'s orders', function (): void {
    it('shows only their orders, with «پرداخت» on pending ones', function (): void {
        $this->actingAs($this->customer, 'customer');
        Order::factory()->create([Order::COLUMN_CUSTOMER_ID => $this->customer->getId(), Order::COLUMN_NUMBER => 'PH-140508-000001']);
        Order::factory()->paid()->create([Order::COLUMN_CUSTOMER_ID => $this->customer->getId(), Order::COLUMN_NUMBER => 'PH-140508-000002']);
        Order::factory()->create([Order::COLUMN_NUMBER => 'PH-140508-000003']);

        livewire(OrderList::class)
            ->assertSee('PH-140508-000001')
            ->assertSee('PH-140508-000002')
            ->assertDontSee('PH-140508-000003')
            ->assertSeeHtml('wire:click="pay(')
            ->assertSee('لغو سفارش');
    });

    it('lets the customer cancel a paid or processing order, marking the money to refund, but not one on its way', function (): void {
        $this->actingAs($this->customer, 'customer');
        $paid = Order::factory()->paid()->create([Order::COLUMN_CUSTOMER_ID => $this->customer->getId()]);
        $processing = Order::factory()->paid()->create([Order::COLUMN_CUSTOMER_ID => $this->customer->getId(), Order::COLUMN_STATUS => OrderStatus::Processing]);
        $onTheWay = Order::factory()->paid()->create([Order::COLUMN_CUSTOMER_ID => $this->customer->getId(), Order::COLUMN_STATUS => OrderStatus::OnTheWay]);

        livewire(OrderList::class)->call('cancel', $paid->getId());
        livewire(OrderList::class)->call('cancel', $processing->getId());
        livewire(OrderList::class)->call('cancel', $onTheWay->getId());

        expect($paid->refresh()->getStatus())->toBe(OrderStatus::Cancelled)
            ->and($paid->getPaymentStatus())->toBe(PaymentStatus::RefundPending)
            ->and($processing->refresh()->getStatus())->toBe(OrderStatus::Cancelled)
            ->and($processing->getPaymentStatus())->toBe(PaymentStatus::RefundPending)
            ->and($onTheWay->refresh()->getStatus())->toBe(OrderStatus::OnTheWay);
    });

    it('offers «لغو سفارش» on the order page only until the order is on its way', function (OrderStatus $status, bool $offered): void {
        $this->actingAs($this->customer, 'customer');
        $order = Order::factory()->paid()->create([Order::COLUMN_CUSTOMER_ID => $this->customer->getId(), Order::COLUMN_STATUS => $status]);

        $page = livewire(OrderDetail::class, ['order' => $order->getId()]);

        $offered ? $page->assertSee('لغو سفارش') : $page->assertDontSee('لغو سفارش');
    })->with([
        'paid' => [OrderStatus::Paid, true],
        'processing' => [OrderStatus::Processing, true],
        'on the way' => [OrderStatus::OnTheWay, false],
        'delivered' => [OrderStatus::Delivered, false],
    ]);

    it('puts the still-available items back into the cart at today\'s price and reports the rest', function (): void {
        $this->actingAs($this->customer, 'customer');
        $available = checkoutSku(1_000_000, 'کرم اوره');
        $gone = checkoutSku(2_000_000, 'سرم قدیمی');
        $order = Order::factory()->paid()->create([Order::COLUMN_CUSTOMER_ID => $this->customer->getId()]);
        foreach ([[$available, 3], [$gone, 1]] as [$variant, $quantity]) {
            (new OrderItem)->setOrderId($order->getId())->setVariantId($variant->getId())->setProductName($variant->product?->getNameFa() ?? '')->setSku($variant->getSku())
                ->setQuantity($quantity)->setListPrice(500_000)->setUnitPrice(500_000)->setPaidTotal(500_000 * $quantity)->setProductSnapshot([])->save();
        }
        $gone->setStockStatus(StockStatus::OutOfStock)->save();

        livewire(OrderList::class)->call('reorder', $order->getId())->assertRedirect(route('cart'));

        expect(CartItem::query()->sole())->getVariantId()->toBe($available->getId())->getQuantity()->toBe(3)
            ->and(session('reorder.message'))->toContain('۱ کالا دیگر موجود نیست');
    });

    it('never shows another customer\'s payment result', function (): void {
        $this->actingAs($this->customer, 'customer');
        $theirs = Order::factory()->create();

        livewire(PaymentResult::class, ['order' => $theirs->getId()])->assertNotFound();
    });

    it('never pays, cancels or reorders another customer\'s order', function (string $action): void {
        $this->actingAs($this->customer, 'customer');
        $theirs = Order::factory()->paid()->create();

        livewire(OrderList::class)->call($action, $theirs->getId())->assertNotFound();

        expect($theirs->fresh()->getStatus())->toBe(OrderStatus::Paid)
            ->and(CartItem::query()->count())->toBe(0);
    })->with(['pay', 'cancel', 'reorder']);

    it('opens an order from the list and shows it from its own copies', function (): void {
        $this->actingAs($this->customer, 'customer');
        $order = Order::factory()->paid()->create([
            Order::COLUMN_CUSTOMER_ID => $this->customer->getId(),
            Order::COLUMN_NUMBER => 'PH-140508-000777',
            Order::COLUMN_ADDRESS_SNAPSHOT => ['title' => 'خانه', 'province' => 'تهران', 'city' => 'تهران', 'main_street' => 'ولیعصر', 'plaque' => '12', 'receiver_name' => 'سارا رضایی', 'receiver_mobile' => '09123456789', 'postal_code' => '1415934571'],
        ]);
        (new OrderItem)->setOrderId($order->getId())->setProductName('کرم اوره قدیمی')->setSku('SKU-1')->setQuantity(2)
            ->setListPrice(500_000)->setUnitPrice(500_000)->setPaidTotal(1_000_000)->setProductSnapshot([])->save();

        livewire(OrderList::class)->assertSee(route('account.orders.show', $order->getId()), false);
        livewire(OrderDetail::class, ['order' => $order->getId()])
            ->assertSee('همه سفارش‌ها')
            ->assertDontSee('حساب من')
            ->assertSee(['PH-140508-000777', 'کرم اوره قدیمی', 'تهران، تهران، خیابان ولیعصر، پلاک ۱۲', 'سارا رضایی', 'لغو سفارش']);
    });

    it('never opens another customer\'s order', function (): void {
        $this->actingAs($this->customer, 'customer');

        livewire(OrderDetail::class, ['order' => Order::factory()->create()->getId()])->assertNotFound();
    });

    it('lists only the customer\'s own orders', function (): void {
        $this->actingAs($this->customer, 'customer');
        Order::factory()->create([Order::COLUMN_CUSTOMER_ID => $this->customer->getId(), Order::COLUMN_NUMBER => 'PH-MINE-1']);
        Order::factory()->create([Order::COLUMN_NUMBER => 'PH-THEIRS-1']);

        livewire(OrderList::class)->assertSee('PH-MINE-1')->assertDontSee('PH-THEIRS-1');
    });
});

describe('order statuses', function (): void {
    it('allows only the agreed moves per actor', function (OrderStatus $from, OrderStatus $to, OrderActor $actor, bool $allowed): void {
        expect($from->canMoveTo($to, $actor))->toBe($allowed);
    })->with([
        'system pays a pending order' => [OrderStatus::Pending, OrderStatus::Paid, OrderActor::System, true],
        'system expires a pending order' => [OrderStatus::Pending, OrderStatus::Expired, OrderActor::System, true],
        'admin processes a paid order' => [OrderStatus::Paid, OrderStatus::Processing, OrderActor::Admin, true],
        'customer cancels a paid order' => [OrderStatus::Paid, OrderStatus::Cancelled, OrderActor::Customer, true],
        'customer cancels a processing order' => [OrderStatus::Processing, OrderStatus::Cancelled, OrderActor::Customer, true],
        'customer cannot cancel on the way' => [OrderStatus::OnTheWay, OrderStatus::Cancelled, OrderActor::Customer, false],
        'admin cancels processing' => [OrderStatus::Processing, OrderStatus::Cancelled, OrderActor::Admin, true],
        'admin cannot cancel on the way' => [OrderStatus::OnTheWay, OrderStatus::Cancelled, OrderActor::Admin, false],
        'admin cancels delivered' => [OrderStatus::Delivered, OrderStatus::Cancelled, OrderActor::Admin, true],
        'customer cannot cancel delivered' => [OrderStatus::Delivered, OrderStatus::Cancelled, OrderActor::Customer, false],
        'customer cannot mark processing' => [OrderStatus::Paid, OrderStatus::Processing, OrderActor::Customer, false],
        'admin delivers' => [OrderStatus::OnTheWay, OrderStatus::Delivered, OrderActor::Admin, true],
    ]);
});
