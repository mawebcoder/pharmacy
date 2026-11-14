<?php

declare(strict_types=1);

namespace App\Modules\Cart\Livewire;

use App\Modules\Cart\Actions\AcknowledgeCartPrices;
use App\Modules\Cart\Actions\RemoveUnavailableCartItems;
use App\Modules\Cart\Data\CartOwnerData;
use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Queries\CurrentCart;
use App\Modules\Catalog\Queries\CategoryTree;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

/**
 * «سبد خرید» (roadmap B9.6, D13.3, S19). Every visit reads current prices and stock: a changed price is shown on its
 * line once («قیمت از … به … تغییر کرد») and then taken as seen; a line that can no longer be sold is greyed, left out
 * of the total, and blocks «ادامه خرید» until «به‌روزرسانی سبد» removes it (owner, 2026-11-14).
 */
final class CartPage extends Component
{
    use Interactions;

    /**
     * Price changes to show on this visit: variant id => price before (rial). Kept while the page is open.
     *
     * @var array<int, int>
     */
    public array $priceChanges = [];

    #[On('cart-updated')]
    public function refresh(): void
    {
        // Re-render with the new quantities and totals.
    }

    public function removeUnavailable(RemoveUnavailableCartItems $removeUnavailable, CurrentCart $carts): void
    {
        if ($removeUnavailable(CartOwnerData::current()) > 0) {
            $this->dispatch('cart-updated', ...$carts->state(CartOwnerData::current()));
        }
    }

    public function proceed(CurrentCart $carts): void
    {
        $summary = $carts->summary(CartOwnerData::current());

        if ($summary->isEmpty() || $summary->hasUnavailable()) {
            return;
        }

        if (auth('customer')->guest()) {
            session()->put('url.intended', route('cart'));
            $this->redirectRoute('login', navigate: true);

            return;
        }

        // Checkout (address → shipping → payment) arrives with S25.
        $this->toast()->info('مرحله انتخاب آدرس و پرداخت به‌زودی فعال می‌شود.')->send();
    }

    public function render(CurrentCart $carts, AcknowledgeCartPrices $acknowledgePrices, CategoryTree $categories): View
    {
        $owner = CartOwnerData::current();
        $cart = $carts->find($owner);
        $summary = $cart instanceof Cart ? $carts->summaryOf($cart) : $carts->summary($owner);

        foreach ($summary->lines as $line) {
            if ($line->previousPrice !== null) {
                $this->priceChanges[$line->variant->variantId] = $line->previousPrice;
            }
        }

        if ($cart instanceof Cart) {
            $acknowledgePrices($cart, $summary);
        }

        return view('cart::livewire.cart-page', [
            'summary' => $summary,
            'popularCategories' => array_slice($categories->roots(), 0, 6),
        ])->title('سبد خرید');
    }
}
