<?php

declare(strict_types=1);

use App\Modules\Cart\Actions\AddToCart;
use App\Modules\Cart\Actions\MergeGuestCart;
use App\Modules\Cart\Data\CartOwnerData;
use App\Modules\Cart\Exceptions\CartRefused;
use App\Modules\Cart\Jobs\PruneGuestCarts;
use App\Modules\Cart\Livewire\CartPage;
use App\Modules\Cart\Livewire\CartState;
use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Models\CartItem;
use App\Modules\Cart\Queries\CurrentCart;
use App\Modules\Cart\Support\CartCookie;
use App\Modules\Catalog\Enums\StockStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Identity\Events\CustomerLoggedIn;
use App\Modules\Identity\Models\Customer;
use App\Modules\Settings\Actions\SaveStoreSettings;
use App\Modules\Settings\Queries\StoreSettings;
use Illuminate\Support\Facades\Cookie;
use Illuminate\View\ComponentAttributeBag;

use function Pest\Livewire\livewire;

/**
 * An active, in-stock SKU at a known price (rial), with overrides for the SKU and its product.
 */
function buyable(array $variant = [], array $product = []): ProductVariant
{
    return ProductVariant::factory()
        ->for(Product::factory()->active()->create([Product::COLUMN_NAME_FA => 'کرم اوره', ...$product]))
        ->create([ProductVariant::COLUMN_OFFICIAL_PRICE => null, ProductVariant::COLUMN_PRICE => 1_000_000, ...$variant]);
}

function customerOwner(Customer $customer): CartOwnerData
{
    return new CartOwnerData($customer->getId(), null);
}

/**
 * @return array<int, int> variant id => quantity
 */
function cartQuantities(Cart $cart): array
{
    return CartItem::query()->where(CartItem::COLUMN_CART_ID, $cart->getId())->pluck(CartItem::COLUMN_QUANTITY, CartItem::COLUMN_VARIANT_ID)->map(intval(...))->all();
}

describe('guest cart cookie', function (): void {
    it('creates the cart on the first add with an HttpOnly cookie whose token is never stored, only its hash', function (): void {
        $variant = buyable();

        expect(app(AddToCart::class)(new CartOwnerData(null, null), $variant->getId()))->toBe(1);

        $cookie = collect(Cookie::getQueuedCookies())->first(fn ($cookie): bool => $cookie->getName() === CartCookie::NAME);
        $cart = Cart::query()->sole();
        expect($cookie)->not->toBeNull()
            ->and($cookie->isHttpOnly())->toBeTrue()
            ->and($cookie->getSameSite())->toBe('lax')
            ->and(strlen((string) $cookie->getValue()))->toBe(40)
            ->and($cart->getSessionTokenHash())->toBe(hash('sha256', (string) $cookie->getValue()))
            ->and(Cart::query()->where(Cart::COLUMN_SESSION_TOKEN_HASH, $cookie->getValue())->exists())->toBeFalse()
            ->and($cart->getId())->not->toBe((int) $cookie->getValue());
    });

    it('finds the guest cart again from the cookie, and not after 30 days', function (): void {
        $variant = buyable();
        $token = str_repeat('a', 40);
        expect(app(AddToCart::class)(new CartOwnerData(null, $token), $variant->getId()))->toBe(1);

        $owner = new CartOwnerData(null, $token);
        $now = app(CurrentCart::class)->summary($owner)->itemCount;
        $this->travel(31)->days();

        expect($now)->toBe(1)
            ->and(app(CurrentCart::class)->summary($owner)->itemCount)->toBe(0);
    });

    it('treats a deleted customer\'s leftover session as a guest', function (): void {
        $customer = Customer::factory()->create();
        auth('customer')->login($customer);
        $customer->delete();
        // The next request finds only the id in the session, not the account.
        auth('customer')->forgetUser();

        expect(auth('customer')->id())->toBe($customer->getId())
            ->and(CartOwnerData::current()->isGuest())->toBeTrue();
    });
});

describe('adding', function (): void {
    it('refuses SKUs that cannot be bought', function (string $case, string $reason): void {
        $variant = match ($case) {
            'out of stock' => buyable([ProductVariant::COLUMN_STOCK_STATUS => StockStatus::OutOfStock]),
            'cold chain' => buyable([ProductVariant::COLUMN_REQUIRES_COLD_CHAIN => true]),
            'inactive SKU' => buyable([ProductVariant::COLUMN_IS_ACTIVE => false]),
            'prescription' => ProductVariant::factory()->for(Product::factory()->active()->rx())->create(),
        };

        expect(fn () => app(AddToCart::class)(new CartOwnerData(null, null), $variant->getId()))->toThrow(CartRefused::class, $reason)
            ->and(Cart::query()->count())->toBe(0);
    })->with([
        ['out of stock', 'موجود نیست'],
        ['cold chain', 'زنجیره سرد'],
        ['inactive SKU', 'دیگر فروخته نمی‌شود'],
        ['prescription', 'نسخه‌ای'],
    ]);

    it('caps the quantity at the SKU limit, else the product limit, else the store setting', function (array $variant, array $product, ?int $store, int $limit): void {
        if ($store !== null) {
            app(SaveStoreSettings::class)([StoreSettings::MAX_QUANTITY_PER_ORDER => $store]);
        }
        $sku = buyable($variant, $product);
        $owner = new CartOwnerData(null, str_repeat('b', 40));

        expect(app(AddToCart::class)($owner, $sku->getId(), 50))->toBe($limit)
            ->and(fn () => app(AddToCart::class)($owner, $sku->getId()))->toThrow(CartRefused::class, 'حداکثر');
    })->with([
        'SKU' => [[ProductVariant::COLUMN_MAX_PER_ORDER => 2], [Product::COLUMN_MAX_PER_ORDER => 5], 7, 2],
        'product' => [[], [Product::COLUMN_MAX_PER_ORDER => 5], 7, 5],
        'store setting' => [[], [], 7, 7],
        'default 10' => [[], [], null, 10],
    ]);

    it('updates the header store and sends the new quantities and limits', function (): void {
        $sku = buyable();

        livewire(CartState::class)
            ->dispatch('cart-add', variantId: $sku->getId())
            ->assertDispatched('cart-updated', items: [$sku->getId() => 1], limits: [$sku->getId() => 10], count: 1, total: 1_000_000)
            ->dispatch('cart-set', variantId: $sku->getId(), quantity: 0)
            ->assertDispatched('cart-updated', count: 0);
    });

    it('shows the free-shipping progress after an add only when 40 % or less is left', function (int $price, bool $shown): void {
        app(SaveStoreSettings::class)([StoreSettings::FREE_SHIPPING_THRESHOLD => 5_000_000]);
        $sku = buyable([ProductVariant::COLUMN_PRICE => $price]);

        $component = livewire(CartState::class)->dispatch('cart-add', variantId: $sku->getId());

        $shown
            ? $component->assertDispatched('ts-ui:toast', fn (string $name, array $params): bool => str_contains((string) ($params['description'] ?? ''), 'تا ارسال رایگان'))
            : $component->assertNotDispatched('ts-ui:toast');
    })->with([
        'more than 40 % left' => [2_000_000, false],
        '40 % or less left' => [3_500_000, true],
        'already free' => [6_000_000, false],
    ]);
});

describe('cart page', function (): void {
    it('shows an empty cart with categories to start from', function (): void {
        $this->get('/cart')->assertOk()->assertSee('سبد خرید شما خالی است');
    });

    it('shows a price change once, then the new price only', function (): void {
        $customer = Customer::factory()->create();
        $sku = buyable([ProductVariant::COLUMN_PRICE => 1_250_000]);
        expect(app(AddToCart::class)(customerOwner($customer), $sku->getId()))->toBe(1);
        $sku->setPrice(1_100_000)->save();
        $this->actingAs($customer, 'customer');

        livewire(CartPage::class)->assertSee('قیمت این کالا از')->assertSee('کاهش');

        livewire(CartPage::class)->assertDontSee('قیمت این کالا از');
    });

    it('greys out a line that can no longer be sold, leaves it out of the total and blocks checkout until the cart is updated', function (): void {
        $customer = Customer::factory()->create();
        $kept = buyable([ProductVariant::COLUMN_PRICE => 1_000_000]);
        $gone = buyable([ProductVariant::COLUMN_PRICE => 2_000_000], [Product::COLUMN_NAME_FA => 'ژل']);
        expect(app(AddToCart::class)(customerOwner($customer), $kept->getId()))->toBe(1)
            ->and(app(AddToCart::class)(customerOwner($customer), $gone->getId()))->toBe(1);
        $gone->setStockStatus(StockStatus::OutOfStock)->save();
        $this->actingAs($customer, 'customer');

        $summary = app(CurrentCart::class)->summary(customerOwner($customer));
        expect($summary->subtotal)->toBe(1_000_000)->and($summary->hasUnavailable())->toBeTrue();

        livewire(CartPage::class)
            ->assertSee('به‌روزرسانی سبد')
            ->assertSeeHtml('disabled')
            ->call('removeUnavailable')
            ->assertDontSee('به‌روزرسانی سبد');

        expect(cartQuantities(app(CurrentCart::class)->find(customerOwner($customer))))->toBe([$kept->getId() => 1]);
    });

    it('sends a guest to sign in before checkout', function (): void {
        $sku = buyable();
        $token = str_repeat('c', 40);
        expect(app(AddToCart::class)(new CartOwnerData(null, $token), $sku->getId()))->toBe(1);

        Livewire\Livewire::withCookie(CartCookie::NAME, $token)->test(CartPage::class)->call('proceed')->assertRedirect(route('login'));
    });

    it('shows the add-to-cart button on in-stock product cards only', function (): void {
        $inStock = buyable([], [Product::COLUMN_NAME_FA => 'موجود', Product::COLUMN_SLUG => 'in-stock']);
        $outOfStock = buyable([ProductVariant::COLUMN_STOCK_STATUS => StockStatus::OutOfStock], [Product::COLUMN_NAME_FA => 'ناموجود', Product::COLUMN_SLUG => 'out-of-stock']);

        $card = fn (ProductVariant $variant): string => view('catalog::components.product-card', ['product' => $variant->getProduct()?->load(['brand', 'media', 'variants']), 'quickView' => false, 'href' => null, 'attributes' => new ComponentAttributeBag])->render();

        expect($card($inStock))->toContain('به سبد خرید')
            ->and($card($outOfStock))->not->toContain('به سبد خرید');
    });
});

describe('signing in', function (): void {
    it('gives the guest cart to a customer who has none and clears its token', function (): void {
        $customer = Customer::factory()->create();
        $sku = buyable();
        $token = str_repeat('d', 40);
        expect(app(AddToCart::class)(new CartOwnerData(null, $token), $sku->getId()))->toBe(1);
        $guestCartId = Cart::query()->sole()->getId();

        app(MergeGuestCart::class)($token, $customer->getId());

        $cart = Cart::query()->sole();
        expect($cart->getId())->toBe($guestCartId)
            ->and($cart->getCustomerId())->toBe($customer->getId())
            ->and($cart->getSessionTokenHash())->toBeNull();
    });

    it('merges the guest cart into the customer\'s, adding and capping quantities, and deletes it', function (): void {
        $customer = Customer::factory()->create();
        $both = buyable([ProductVariant::COLUMN_MAX_PER_ORDER => 3]);
        $guestOnly = buyable([], [Product::COLUMN_NAME_FA => 'ژل']);
        $token = str_repeat('e', 40);
        expect(app(AddToCart::class)(customerOwner($customer), $both->getId(), 2))->toBe(2)
            ->and(app(AddToCart::class)(new CartOwnerData(null, $token), $both->getId(), 2))->toBe(2)
            ->and(app(AddToCart::class)(new CartOwnerData(null, $token), $guestOnly->getId()))->toBe(1);

        app(MergeGuestCart::class)($token, $customer->getId());

        $cart = Cart::query()->sole();
        expect($cart->getCustomerId())->toBe($customer->getId())
            ->and(cartQuantities($cart))->toEqualCanonicalizing([$both->getId() => 3, $guestOnly->getId() => 1]);
    });

    it('merges on the sign-in event using the cookie', function (): void {
        $customer = Customer::factory()->create();
        $sku = buyable();
        $token = str_repeat('f', 40);
        expect(app(AddToCart::class)(new CartOwnerData(null, $token), $sku->getId()))->toBe(1);
        request()->cookies->set(CartCookie::NAME, $token);

        event(new CustomerLoggedIn($customer->getId(), isNewCustomer: false));

        expect(Cart::query()->sole()->getCustomerId())->toBe($customer->getId());
    });
});

it('deletes expired guest carts and keeps customers\' carts', function (): void {
    $customer = Customer::factory()->create();
    $expiredGuest = Cart::factory()->create([Cart::COLUMN_EXPIRES_AT => now()->subDay()]);
    $freshGuest = Cart::factory()->create();
    $customerCart = Cart::factory()->create([Cart::COLUMN_CUSTOMER_ID => $customer->getId(), Cart::COLUMN_SESSION_TOKEN_HASH => null, Cart::COLUMN_EXPIRES_AT => null]);

    (new PruneGuestCarts)->handle();

    expect(Cart::query()->pluck(Cart::COLUMN_ID)->all())->toEqualCanonicalizing([$freshGuest->getId(), $customerCart->getId()])
        ->and($expiredGuest->fresh())->toBeNull();
});

describe('quantities endpoint', function (): void {
    it('returns just the quantity of each SKU in the shopper\'s cart, never cached', function (): void {
        $customer = Customer::factory()->create();
        $first = buyable();
        $second = buyable([], [Product::COLUMN_NAME_FA => 'ژل']);
        expect(app(AddToCart::class)(customerOwner($customer), $first->getId(), 2))->toBe(2)
            ->and(app(AddToCart::class)(customerOwner($customer), $second->getId()))->toBe(1);

        $this->actingAs($customer, 'customer')
            ->getJson(route('cart.quantities'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertExactJson(['data' => ['items' => [(string) $first->getId() => 2, (string) $second->getId() => 1], 'count' => 3]]);
    });

    it('reads a guest cart from the cookie and returns an empty map without one', function (): void {
        $sku = buyable();
        $token = str_repeat('g', 40);
        expect(app(AddToCart::class)(new CartOwnerData(null, $token), $sku->getId()))->toBe(1);

        // getJson sends cookies only with credentials, like a same-origin fetch.
        $this->withCredentials()->getJson(route('cart.quantities'))->assertExactJson(['data' => ['items' => [], 'count' => 0]]);
        $this->withCredentials()->withCookie(CartCookie::NAME, $token)->getJson(route('cart.quantities'))->assertJson(['data' => ['count' => 1]]);
    });
});
