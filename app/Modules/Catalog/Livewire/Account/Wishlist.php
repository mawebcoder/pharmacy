<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Livewire\Account;

use App\Modules\Catalog\Actions\RemoveFromWishlist;
use App\Modules\Catalog\Queries\CustomerWishlist;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use TallStackUi\Traits\Interactions;

/**
 * «علاقه‌مندی‌ها» (roadmap B9.9, D3.3, S18): the signed-in customer's saved products as product cards, newest saved
 * first, 24 per page, each with «حذف». Products no longer on the shop are left out.
 */
final class Wishlist extends Component
{
    use Interactions;
    use WithPagination;

    public function remove(int $productId, RemoveFromWishlist $removeFromWishlist): void
    {
        $removeFromWishlist($this->customerId(), $productId);
        $this->dispatch('wishlist-updated', productId: $productId, saved: false);
        $this->toast()->info('از علاقه‌مندی‌ها حذف شد')->send();
    }

    public function render(CustomerWishlist $wishlist): View
    {
        return view('catalog::livewire.account.wishlist', [
            'products' => $wishlist->products($this->customerId()),
        ])->title('علاقه‌مندی‌ها');
    }

    private function customerId(): int
    {
        return (int) (auth('customer')->user()?->getAuthIdentifier() ?? abort(401));
    }
}
