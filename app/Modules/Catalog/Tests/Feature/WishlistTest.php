<?php

declare(strict_types=1);

use App\Modules\Catalog\Actions\AddToWishlist;
use App\Modules\Catalog\Actions\ToggleWishlist;
use App\Modules\Catalog\Livewire\Account\Wishlist as WishlistPage;
use App\Modules\Catalog\Livewire\WishlistToggle;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\Wishlist;
use App\Modules\Catalog\Support\PendingWishlist;
use App\Modules\Identity\Events\CustomerLoggedIn;
use App\Modules\Identity\Models\Customer;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->customer = Customer::factory()->create();
    $this->product = Product::factory()->active()->create([Product::COLUMN_NAME_FA => 'کرم اوره']);
    ProductVariant::factory()->for($this->product)->create();
});

function savedProductIds(int $customerId): array
{
    return Wishlist::query()->where(Wishlist::COLUMN_CUSTOMER_ID, $customerId)->pluck(Wishlist::COLUMN_PRODUCT_ID)->map(intval(...))->all();
}

it('saves a product on the first heart click and removes it on the second', function (): void {
    $this->actingAs($this->customer, 'customer');

    livewire(WishlistToggle::class)->call('toggle', $this->product->getId())->assertDispatched('wishlist-updated', productId: $this->product->getId(), saved: true);
    $afterFirst = savedProductIds($this->customer->getId());
    livewire(WishlistToggle::class)->call('toggle', $this->product->getId())->assertDispatched('wishlist-updated', saved: false);

    expect($afterFirst)->toBe([$this->product->getId()])
        ->and(savedProductIds($this->customer->getId()))->toBe([]);
});

it('keeps one row per product and refuses products that are not on the shop', function (): void {
    $draft = Product::factory()->create();

    $first = app(AddToWishlist::class)($this->customer->getId(), $this->product->getId());
    $again = app(AddToWishlist::class)($this->customer->getId(), $this->product->getId());
    $inactive = app(ToggleWishlist::class)($this->customer->getId(), $draft->getId());

    expect([$first, $again, $inactive])->toBe([true, true, false])
        ->and(savedProductIds($this->customer->getId()))->toBe([$this->product->getId()]);
});

it('sends a guest to sign in and saves the product once they are signed in', function (): void {
    livewire(WishlistToggle::class)->call('toggle', $this->product->getId())->assertRedirect(route('login'));

    event(new CustomerLoggedIn($this->customer->getId(), isNewCustomer: false));

    expect(savedProductIds($this->customer->getId()))->toBe([$this->product->getId()])
        ->and(session(PendingWishlist::SAVED_FLASH))->toBeTrue();
});

it('seeds the heart buttons with the customer\'s saved products', function (): void {
    expect(app(AddToWishlist::class)($this->customer->getId(), $this->product->getId()))->toBeTrue();
    $this->actingAs($this->customer, 'customer');

    livewire(WishlistToggle::class)->assertSet('productIds', [$this->product->getId()]);
});

it('lists saved products on the wishlist page and removes one', function (): void {
    expect(app(AddToWishlist::class)($this->customer->getId(), $this->product->getId()))->toBeTrue();
    $this->actingAs($this->customer, 'customer');

    livewire(WishlistPage::class)
        ->assertSee('کرم اوره')
        ->call('remove', $this->product->getId())
        ->assertDispatched('wishlist-updated', saved: false)
        ->assertSee('هنوز محصولی را ذخیره نکرده‌اید.');

    expect(savedProductIds($this->customer->getId()))->toBe([]);
});

it('shows a heart button on product cards', function (): void {
    $this->get(route('products.show', $this->product))->assertOk()->assertSee('افزودن به علاقه‌مندی‌ها');
});
