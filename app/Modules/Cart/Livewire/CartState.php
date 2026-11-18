<?php

declare(strict_types=1);

namespace App\Modules\Cart\Livewire;

use App\Modules\Cart\Actions\AddToCart;
use App\Modules\Cart\Actions\EmptyCart;
use App\Modules\Cart\Actions\SetCartQuantity;
use App\Modules\Cart\Data\CartOwnerData;
use App\Modules\Cart\Exceptions\CartRefused;
use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Queries\CurrentCart;
use App\Modules\Settings\Queries\StoreSettings;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

/**
 * Behind every add-to-cart button (roadmap S21), once per page: seeds the Alpine «cart» store (quantity and limit per
 * SKU, item count, total) and handles the `cart-add` / `cart-set` / `cart-clear` browser events, then sends `cart-updated`
 * with the new state. No toast on a change (the stepper and header count show it; owner, 2026-11-14), except the free-shipping
 * progress when it is close; refusals (out of stock, limit, prescription) are shown as warnings.
 */
final class CartState extends Component
{
    use Interactions;

    /**
     * Progress (percent of the free-shipping amount) from which an add shows the free-shipping toast.
     */
    private const int FREE_SHIPPING_NUDGE_FROM = 60;

    /** @var array{items: array<int, int>, limits: array<int, int>, count: int, total: int} */
    public array $state = ['items' => [], 'limits' => [], 'count' => 0, 'total' => 0];

    public function mount(CurrentCart $carts): void
    {
        $this->state = $this->stateOf($carts);
    }

    #[On('cart-add')]
    public function add(int $variantId, AddToCart $addToCart, CurrentCart $carts): void
    {
        try {
            $quantity = $addToCart(CartOwnerData::current(), $variantId);
        } catch (CartRefused $refused) {
            $this->toast()->warning($refused->getMessage())->send();

            return;
        } finally {
            $this->settle($variantId);
        }

        $previousSubtotal = $this->state['total'];
        $this->publish($carts, $quantity);
        $this->nudgeTowardsFreeShipping($carts, $previousSubtotal);
    }

    #[On('cart-set')]
    public function set(int $variantId, int $quantity, SetCartQuantity $setQuantity, CurrentCart $carts): void
    {
        try {
            $quantity = $setQuantity(CartOwnerData::current(), $variantId, $quantity);
        } catch (CartRefused $refused) {
            $this->toast()->warning($refused->getMessage())->send();
        } finally {
            $this->settle($variantId);
        }

        $this->publish($carts, $quantity);
    }

    /**
     * «حذف همه» on the cart page and the mini cart (owner, 2026-11-15): empties the current owner's cart.
     */
    #[On('cart-clear')]
    public function clear(EmptyCart $emptyCart, CurrentCart $carts): void
    {
        $cart = $carts->find(CartOwnerData::current());

        if (! $cart instanceof Cart) {
            return;
        }

        $emptyCart($cart->getId());
        $this->publish($carts, 0);
    }

    public function render(StoreSettings $settings): View
    {
        return view('cart::livewire.cart-state', ['removalDelaySeconds' => $settings->removalDelaySeconds()]);
    }

    /**
     * Tells the SKU's add-to-cart buttons the change is done, accepted or refused, so they stop their spinner.
     */
    private function settle(int $variantId): void
    {
        $this->dispatch('cart-settled', variantId: $variantId);
    }

    /**
     * Sends the new state, with the changed SKU's new quantity for listeners that want it.
     */
    private function publish(CurrentCart $carts, int $changedQuantity): void
    {
        $this->state = $this->stateOf($carts);
        $this->dispatch('cart-updated', ...$this->state, changedQuantity: $changedQuantity);
    }

    /**
     * After an add, when 40 % or less of the free-shipping amount is left, a toast shows the progress bar (owner,
     * 2026-11-14). The bar grows from where it was before the add. Nothing when there is no offer, the order already
     * ships free, or more is left.
     */
    private function nudgeTowardsFreeShipping(CurrentCart $carts, int $previousSubtotal): void
    {
        $summary = $carts->summary(CartOwnerData::current());

        if ($summary->freeShippingThreshold === 0 || $summary->hasFreeShipping() || $summary->freeShippingProgress() < self::FREE_SHIPPING_NUDGE_FROM) {
            return;
        }

        $this->toast()
            ->info('تا ارسال رایگان چیزی نمانده', view('cart::partials.free-shipping-toast', [
                'summary' => $summary,
                'previousProgress' => (int) min(100, floor($previousSubtotal * 100 / $summary->freeShippingThreshold)),
            ])->render())
            ->send();
    }

    /**
     * @return array{items: array<int, int>, limits: array<int, int>, count: int, total: int}
     */
    private function stateOf(CurrentCart $carts): array
    {
        return $carts->state(CartOwnerData::current());
    }
}
