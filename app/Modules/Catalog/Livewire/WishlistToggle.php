<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Livewire;

use App\Modules\Catalog\Actions\ToggleWishlist;
use App\Modules\Catalog\Queries\CustomerWishlist;
use App\Modules\Catalog\Support\PendingWishlist;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

/**
 * Behind every heart button (roadmap S18), once per page: seeds the Alpine «wishlist» store with the customer's saved
 * product ids and handles the `wishlist-toggle` browser event. A guest is sent to sign in; the product is remembered
 * and saved after sign-in (SavePendingWishlistProduct), then they come back to this page.
 */
final class WishlistToggle extends Component
{
    use Interactions;

    /** @var list<int> */
    public array $productIds = [];

    public function mount(CustomerWishlist $wishlist): void
    {
        $customerId = auth('customer')->user()?->getAuthIdentifier();
        $this->productIds = $customerId === null ? [] : $wishlist->productIds((int) $customerId);

        if (session()->pull(PendingWishlist::SAVED_FLASH) === true) {
            $this->toast()->success('به علاقه‌مندی‌ها اضافه شد')->send();
        }
    }

    #[On('wishlist-toggle')]
    public function toggle(int $productId, ToggleWishlist $toggleWishlist): void
    {
        $customerId = auth('customer')->user()?->getAuthIdentifier();

        if ($customerId === null) {
            PendingWishlist::remember($productId);
            session()->put('url.intended', url()->previous());
            $this->redirectRoute('login', navigate: true);

            return;
        }

        $saved = $toggleWishlist((int) $customerId, $productId);
        $this->dispatch('wishlist-updated', productId: $productId, saved: $saved);
        $saved
            ? $this->toast()->success('به علاقه‌مندی‌ها اضافه شد')->send()
            : $this->toast()->info('از علاقه‌مندی‌ها حذف شد')->send();
    }

    public function render(): View
    {
        return view('catalog::livewire.wishlist-toggle');
    }
}
