<?php

declare(strict_types=1);

use App\Modules\Cart\Actions\AddToCart;
use App\Modules\Cart\Actions\ApplyCouponAtCheckout;
use App\Modules\Cart\Actions\MarkCouponUsed;
use App\Modules\Cart\Actions\QuoteCoupon;
use App\Modules\Cart\Actions\ReleaseCoupon;
use App\Modules\Cart\Actions\ReserveCoupon;
use App\Modules\Cart\Actions\SaveCoupon;
use App\Modules\Cart\Contracts\CustomerOrderHistory;
use App\Modules\Cart\Data\AppliedCouponData;
use App\Modules\Cart\Data\CartOwnerData;
use App\Modules\Cart\Data\CouponCodeData;
use App\Modules\Cart\Data\CouponData;
use App\Modules\Cart\Enums\CouponType;
use App\Modules\Cart\Enums\CouponUsageStatus;
use App\Modules\Cart\Exceptions\CouponRefused;
use App\Modules\Cart\Jobs\ReleaseExpiredCouponReservations;
use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Models\Coupon;
use App\Modules\Cart\Models\CouponUsage;
use App\Modules\Cart\Queries\CurrentCart;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Identity\Models\Customer;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * An active SKU at a price (rial), optionally on sale, in a brand and category.
 */
function couponSku(int $price, ?int $salePrice = null, ?Brand $brand = null, ?Category $category = null): ProductVariant
{
    $product = Product::factory()->active()->create(array_filter([
        Product::COLUMN_BRAND_ID => $brand?->getId(),
        Product::COLUMN_PRIMARY_CATEGORY_ID => $category?->getId(),
    ]));

    return ProductVariant::factory()->for($product)->create([
        ProductVariant::COLUMN_OFFICIAL_PRICE => null,
        ProductVariant::COLUMN_PRICE => $price,
        ProductVariant::COLUMN_SALE_PRICE => $salePrice,
    ]);
}

/**
 * The customer's cart with these SKUs (quantity 1 each).
 *
 * @param  list<ProductVariant>  $variants
 */
function couponCart(Customer $customer, array $variants): Cart
{
    foreach ($variants as $variant) {
        expect(app(AddToCart::class)(new CartOwnerData($customer->getId(), null), $variant->getId()))->toBe(1);
    }

    return Cart::query()->where(Cart::COLUMN_CUSTOMER_ID, $customer->getId())->sole();
}

/**
 * The discount checkout would show for this code now (writes nothing).
 */
function applyCode(Cart $cart, Customer $customer, string $code): AppliedCouponData
{
    return app(QuoteCoupon::class)(CouponCodeData::fromArray(['cart_id' => $cart->getId(), 'code' => $code, 'customer_id' => $customer->getId()]));
}

function usageStatuses(Coupon $coupon): array
{
    return CouponUsage::query()->where(CouponUsage::COLUMN_COUPON_ID, $coupon->getId())->orderBy(CouponUsage::COLUMN_ID)->get()->map(fn (CouponUsage $usage): string => $usage->getStatus()->value)->all();
}

/**
 * @param  list<int>  $categoryIds
 * @param  list<int>  $brandIds
 * @param  list<int>  $productIds
 */
function saveCouponLists(Coupon $coupon, array $categoryIds = [], array $brandIds = [], array $productIds = []): void
{
    $data = CouponData::fromArray([
        ...$coupon->only([Coupon::COLUMN_CODE, Coupon::COLUMN_VALUE, Coupon::COLUMN_MAX_DISCOUNT, Coupon::COLUMN_MIN_ORDER_AMOUNT, Coupon::COLUMN_APPLIES_TO_SALE_ITEMS]),
        Coupon::COLUMN_TYPE => $coupon->getType(),
        'category_ids' => $categoryIds,
        'brand_ids' => $brandIds,
        'product_ids' => $productIds,
    ]);

    expect(app(SaveCoupon::class)($data, $coupon)->getId())->toBe($coupon->getId());
}

describe('the discount', function (): void {
    it('gives a percentage rounded down to whole toman, capped by the maximum', function (): void {
        $customer = Customer::factory()->create();
        $cart = couponCart($customer, [couponSku(6_000_005)]);
        Coupon::factory()->percent(10)->create([Coupon::COLUMN_CODE => 'TEN']);
        Coupon::factory()->percent(10, maxDiscount: 400_000)->create([Coupon::COLUMN_CODE => 'CAPPED']);

        expect(applyCode($cart, $customer, 'ten')->discount)->toBe(600_000)
            ->and(applyCode($cart, $customer, 'CAPPED')->discount)->toBe(400_000);
    });

    it('never gives a fixed discount above the covered amount', function (): void {
        $customer = Customer::factory()->create();
        $cart = couponCart($customer, [couponSku(300_000)]);
        Coupon::factory()->fixed(500_000)->create([Coupon::COLUMN_CODE => 'FIXED']);

        $applied = applyCode($cart, $customer, 'FIXED');

        expect($applied->discount)->toBe(300_000)
            ->and(app(CurrentCart::class)->summaryOf($cart)->withCoupon($applied)->total())->toBe(0);
    });

    it('makes shipping free without an item discount', function (): void {
        $customer = Customer::factory()->create();
        $cart = couponCart($customer, [couponSku(1_000_000)]);
        Coupon::factory()->freeShipping()->create([Coupon::COLUMN_CODE => 'SHIPFREE']);

        $applied = applyCode($cart, $customer, 'SHIPFREE');
        $summary = app(CurrentCart::class)->summaryOf($cart)->withCoupon($applied);

        expect($applied->discount)->toBe(0)
            ->and($applied->freeShipping)->toBeTrue()
            ->and($summary->shippingIsFree())->toBeTrue();
    });

    it('accepts codes typed in lower case or with Persian digits', function (): void {
        $customer = Customer::factory()->create();
        $cart = couponCart($customer, [couponSku(1_000_000)]);
        Coupon::factory()->create([Coupon::COLUMN_CODE => 'BAHAR1405']);

        expect(applyCode($cart, $customer, 'bahar۱۴۰۵')->code)->toBe('BAHAR1405');
    });
});

describe('covered items', function (): void {
    it('covers the whole cart when no list is filled', function (): void {
        $customer = Customer::factory()->create();
        $cart = couponCart($customer, [couponSku(1_000_000), couponSku(2_000_000)]);
        Coupon::factory()->percent(10)->create([Coupon::COLUMN_CODE => 'ALL']);

        expect(applyCode($cart, $customer, 'ALL')->coveredSubtotal)->toBe(3_000_000);
    });

    it('covers a listed category with its subcategories, a listed brand or a listed product', function (string $list): void {
        $customer = Customer::factory()->create();
        $skin = Category::factory()->create();
        $creams = Category::factory()->childOf($skin)->create();
        $brand = Brand::factory()->create();
        $covered = couponSku(1_000_000, brand: $brand, category: $creams);
        $other = couponSku(4_000_000);
        $cart = couponCart($customer, [$covered, $other]);
        $coupon = Coupon::factory()->percent(10)->create([Coupon::COLUMN_CODE => 'LIMITED']);

        match ($list) {
            'category' => saveCouponLists($coupon, categoryIds: [$skin->getId()]),
            'brand' => saveCouponLists($coupon, brandIds: [$brand->getId()]),
            default => saveCouponLists($coupon, productIds: [$covered->getProductId()]),
        };

        $applied = applyCode($cart, $customer, 'LIMITED');
        expect($applied->coveredSubtotal)->toBe(1_000_000)
            ->and($applied->discount)->toBe(100_000);
    })->with(['category', 'brand', 'product']);

    it('leaves haraj items out unless the coupon includes them', function (): void {
        $customer = Customer::factory()->create();
        $cart = couponCart($customer, [couponSku(1_000_000), couponSku(2_000_000, salePrice: 1_500_000)]);
        $coupon = Coupon::factory()->percent(10)->create([Coupon::COLUMN_CODE => 'NOSALE']);

        $without = applyCode($cart, $customer, 'NOSALE')->coveredSubtotal;
        $coupon->setAppliesToSaleItems(true)->save();

        expect($without)->toBe(1_000_000)
            ->and(applyCode($cart, $customer, 'NOSALE')->coveredSubtotal)->toBe(2_500_000);
    });

    it('refuses a coupon that covers nothing in the cart', function (): void {
        $customer = Customer::factory()->create();
        $cart = couponCart($customer, [couponSku(1_000_000)]);
        $coupon = Coupon::factory()->create([Coupon::COLUMN_CODE => 'OTHERBRAND']);
        saveCouponLists($coupon, brandIds: [Brand::factory()->create()->getId()]);

        expect(fn (): AppliedCouponData => applyCode($cart, $customer, 'OTHERBRAND'))
            ->toThrow(fn (CouponRefused $refused): bool => $refused->errorCode() === 'coupon_no_covered_items');
    });

    it('checks the minimum against the covered items only', function (): void {
        $customer = Customer::factory()->create();
        $brand = Brand::factory()->create();
        $cart = couponCart($customer, [couponSku(1_000_000, brand: $brand), couponSku(9_000_000)]);
        $coupon = Coupon::factory()->create([Coupon::COLUMN_CODE => 'MIN', Coupon::COLUMN_MIN_ORDER_AMOUNT => 2_000_000]);
        saveCouponLists($coupon, brandIds: [$brand->getId()]);

        expect(fn (): AppliedCouponData => applyCode($cart, $customer, 'MIN'))
            ->toThrow(fn (CouponRefused $refused): bool => $refused->errorCode() === 'coupon_below_minimum' && str_contains($refused->getMessage(), 'کالاهای مشمول'));
    });
});

describe('rules', function (): void {
    it('refuses unknown, inactive, deleted, not started and expired coupons', function (string $state, string $code): void {
        $customer = Customer::factory()->create();
        $cart = couponCart($customer, [couponSku(1_000_000)]);
        $coupon = Coupon::factory()->create([Coupon::COLUMN_CODE => 'RULES']);

        match ($state) {
            'inactive' => $coupon->setIsActive(false)->save(),
            'deleted' => $coupon->delete(),
            'not started' => $coupon->setStartsAt(now()->addDay())->save(),
            'expired' => $coupon->setEndsAt(now()->subMinute())->save(),
            default => $coupon->setCode('ELSE')->save(),
        };

        expect(fn (): AppliedCouponData => applyCode($cart, $customer, 'RULES'))
            ->toThrow(fn (CouponRefused $refused): bool => $refused->errorCode() === $code);
    })->with([
        'unknown' => ['unknown', 'coupon_not_found'],
        'inactive' => ['inactive', 'coupon_not_found'],
        'deleted' => ['deleted', 'coupon_not_found'],
        'not started' => ['not started', 'coupon_not_started'],
        'expired' => ['expired', 'coupon_expired'],
    ]);

    it('applies only to a first order when set', function (): void {
        $customer = Customer::factory()->create();
        $cart = couponCart($customer, [couponSku(1_000_000)]);
        Coupon::factory()->create([Coupon::COLUMN_CODE => 'WELCOME', Coupon::COLUMN_FIRST_ORDER_ONLY => true]);

        expect(applyCode($cart, $customer, 'WELCOME')->isValid())->toBeTrue();

        app()->instance(CustomerOrderHistory::class, new class implements CustomerOrderHistory
        {
            public function hasPlacedOrder(int $customerId): bool
            {
                return true;
            }
        });

        expect(fn (): AppliedCouponData => applyCode($cart, $customer, 'WELCOME'))
            ->toThrow(fn (CouponRefused $refused): bool => $refused->errorCode() === 'coupon_first_order_only');
    });

    it('stops at the per-customer and total limits, counting reserved and used uses', function (): void {
        $first = Customer::factory()->create();
        $second = Customer::factory()->create();
        $coupon = Coupon::factory()->create([Coupon::COLUMN_CODE => 'LIMIT', Coupon::COLUMN_USAGE_LIMIT_PER_CUSTOMER => 1, Coupon::COLUMN_USAGE_LIMIT_TOTAL => 2]);
        $reserve = app(ReserveCoupon::class);

        app(MarkCouponUsed::class)($reserve($coupon->getId(), $first->getId(), 100_000));

        expect(fn (): CouponUsage => $reserve($coupon->getId(), $first->getId(), 100_000))
            ->toThrow(fn (CouponRefused $refused): bool => $refused->errorCode() === 'coupon_already_used');

        expect($reserve($coupon->getId(), $second->getId(), 100_000)->getStatus())->toBe(CouponUsageStatus::Reserved)
            ->and(fn (): CouponUsage => $reserve($coupon->getId(), Customer::factory()->create()->getId(), 100_000))
            ->toThrow(fn (CouponRefused $refused): bool => $refused->errorCode() === 'coupon_exhausted');
    });

    it('locks the coupon row while reserving, so two customers paying at once cannot pass the limit', function (): void {
        $coupon = Coupon::factory()->create([Coupon::COLUMN_CODE => 'LOCKED', Coupon::COLUMN_USAGE_LIMIT_TOTAL => 1]);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        expect(app(ReserveCoupon::class)($coupon->getId(), Customer::factory()->create()->getId(), 50_000)->getCouponId())->toBe($coupon->getId());

        expect(collect($queries)->contains(fn (string $sql): bool => str_contains($sql, 'from `coupons`') && str_contains($sql, 'for update')))->toBeTrue();
    });
});

describe('through checkout and payment', function (): void {
    it('works the discount out on the fly without writing anything', function (): void {
        $customer = Customer::factory()->create();
        $cart = couponCart($customer, [couponSku(2_000_000)]);
        $coupon = Coupon::factory()->percent(10)->create([Coupon::COLUMN_CODE => 'QUOTE']);
        $cartRow = DB::table('carts')->where('id', $cart->getId())->first();

        expect(applyCode($cart, $customer, 'QUOTE')->discount)->toBe(200_000)
            ->and(CouponUsage::query()->count())->toBe(0)
            ->and(DB::table('carts')->where('id', $cart->getId())->first())->toEqual($cartRow)
            ->and(usageStatuses($coupon))->toBe([]);
    });

    it('logs a code applied at checkout once, without counting it toward the limits', function (): void {
        $customer = Customer::factory()->create();
        $cart = couponCart($customer, [couponSku(2_000_000)]);
        $coupon = Coupon::factory()->percent(10)->create([Coupon::COLUMN_CODE => 'ONCE', Coupon::COLUMN_USAGE_LIMIT_TOTAL => 1]);
        $data = CouponCodeData::fromArray(['cart_id' => $cart->getId(), 'code' => 'ONCE', 'customer_id' => $customer->getId()]);

        expect(app(ApplyCouponAtCheckout::class)($data)->discount)->toBe(200_000)
            ->and(app(ApplyCouponAtCheckout::class)($data)->discount)->toBe(200_000)
            ->and(usageStatuses($coupon))->toBe(['checkout']);

        $other = Customer::factory()->create();
        expect(applyCode(couponCart($other, [couponSku(1_000_000)]), $other, 'ONCE')->isValid())->toBeTrue();
    });

    it('turns the checkout log into a reservation that lasts as long as a pending payment', function (): void {
        $this->freezeTime();
        config(['payments.pending_expiry_minutes' => 45]);
        $customer = Customer::factory()->create();
        $cart = couponCart($customer, [couponSku(2_000_000)]);
        $coupon = Coupon::factory()->create([Coupon::COLUMN_CODE => 'GATEWAY']);
        $applied = app(ApplyCouponAtCheckout::class)(CouponCodeData::fromArray(['cart_id' => $cart->getId(), 'code' => 'GATEWAY', 'customer_id' => $customer->getId()]));

        $order = Order::factory()->create([Order::COLUMN_CUSTOMER_ID => $customer->getId()]);
        $usage = app(ReserveCoupon::class)($coupon->getId(), $customer->getId(), $applied->discount, orderId: $order->getId());

        expect(usageStatuses($coupon))->toBe(['reserved'])
            ->and($usage->getOrderId())->toBe($order->getId())
            ->and($usage->getDiscountAmount())->toBe(200_000)
            ->and($usage->getReservedUntil()?->timestamp)->toBe(now()->addMinutes(45)->timestamp);
    });

    it('marks the reservation used after payment and keeps counting it', function (): void {
        $coupon = Coupon::factory()->create([Coupon::COLUMN_CODE => 'PAID', Coupon::COLUMN_USAGE_LIMIT_TOTAL => 1]);
        $usage = app(ReserveCoupon::class)($coupon->getId(), Customer::factory()->create()->getId(), 50_000);
        $this->travel(2)->hours();

        app(MarkCouponUsed::class)($usage);
        app(ReleaseCoupon::class)($usage->refresh());

        expect($usage->refresh()->getStatus())->toBe(CouponUsageStatus::Used)
            ->and($usage->getUsedAt())->not->toBeNull()
            ->and(fn (): CouponUsage => app(ReserveCoupon::class)($coupon->getId(), Customer::factory()->create()->getId(), 50_000))
            ->toThrow(CouponRefused::class);
    });

    it('frees the slot when the payment fails or the reservation expires', function (string $how): void {
        $coupon = Coupon::factory()->create([Coupon::COLUMN_CODE => 'FREED', Coupon::COLUMN_USAGE_LIMIT_TOTAL => 1]);
        $usage = app(ReserveCoupon::class)($coupon->getId(), Customer::factory()->create()->getId(), 50_000);

        if ($how === 'payment failed') {
            app(ReleaseCoupon::class)($usage);
        } else {
            $this->travel((int) config('payments.pending_expiry_minutes') + 1)->minutes();
            // Already free before the job runs; the job records the release.
            expect(app(ReserveCoupon::class)($coupon->getId(), Customer::factory()->create()->getId(), 50_000)->getStatus())->toBe(CouponUsageStatus::Reserved);
            (new ReleaseExpiredCouponReservations)->handle(app(ReleaseCoupon::class));
        }

        expect($usage->refresh()->getStatus())->toBe(CouponUsageStatus::Released)
            ->and($usage->getReleasedAt())->not->toBeNull();
    })->with(['payment failed', 'reservation expired']);
});

describe('saving a coupon', function (): void {
    it('stores the code upper-case and replaces the include lists', function (): void {
        $brand = Brand::factory()->create();
        $coupon = app(SaveCoupon::class)(CouponData::fromArray([
            Coupon::COLUMN_CODE => ' yalda-۱۴۰۵ ',
            Coupon::COLUMN_TYPE => CouponType::Fixed->value,
            Coupon::COLUMN_VALUE => 500_000,
            Coupon::COLUMN_MAX_DISCOUNT => 999,
            'brand_ids' => [$brand->getId(), $brand->getId()],
        ]));

        expect($coupon->getCode())->toBe('YALDA-1405')
            ->and($coupon->getMaxDiscount())->toBeNull()
            ->and($coupon->includedIds('coupon_brands'))->toBe([$brand->getId()]);

        saveCouponLists($coupon);
        expect($coupon->includedIds('coupon_brands'))->toBe([]);
    });
});
