<?php

declare(strict_types=1);

namespace App\Modules\Cart\Livewire;

use App\Modules\Cart\Data\CartOwnerData;
use App\Modules\Cart\Data\CartSummaryData;
use App\Modules\Cart\Queries\CurrentCart;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The desktop mini-cart in the header (roadmap B9.1, S19): the bag with the count and total, and on click a drop-down
 * of the lines. The lines load only when it is first opened and follow every cart change.
 */
final class MiniCart extends Component
{
    public bool $loaded = false;

    public function load(): void
    {
        $this->loaded = true;
    }

    #[On('cart-updated')]
    public function refresh(): void
    {
        // Re-render with the new lines when open.
    }

    public function render(CurrentCart $carts): View
    {
        return view('cart::livewire.mini-cart', [
            'summary' => $this->loaded ? $carts->summary(CartOwnerData::current()) : null,
        ]);
    }

    /**
     * @return array{summary: CartSummaryData|null}
     */
    public function placeholderData(): array
    {
        return ['summary' => null];
    }
}
